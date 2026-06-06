<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Pharmacy Bills — Cashier</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <link href="<?php echo base_url(); ?>public/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo base_url(); ?>public/css/font-awesome.min.css" rel="stylesheet">
    <link href="<?php echo base_url(); ?>public/css/AdminLTE.css" rel="stylesheet">
    <style>
        .table td { vertical-align: middle !important; }
        .badge-status { font-size: 12px; padding: 5px 9px; }
        .badge-pending  { background-color: #e74c3c; color: #fff; }
        .badge-paid     { background-color: #27ae60; color: #fff; }
        .badge-cancelled{ background-color: #95a5a6; color: #fff; }
        .badge-waiting  { background-color: #f39c12; color: #fff; }
        .badge-ready    { background-color: #2980b9; color: #fff; }
        .total-amount   { font-weight: bold; font-size: 14px; }
    </style>
</head>
<body class="skin-blue">
    <?php require_once(APPPATH . 'views/include/header.php'); ?>
    <div class="wrapper row-offcanvas row-offcanvas-left">
        <?php require_once(APPPATH . 'views/include/sidebar.php'); ?>
        <aside class="right-side">
            <section class="content-header">
                <h1><i class="fa fa-medkit"></i> Pending Pharmacy Bills</h1>
                <ol class="breadcrumb">
                    <li><a href="<?php echo base_url(); ?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                    <li><a href="<?php echo base_url(); ?>app/billing">Billing</a></li>
                    <li class="active">Pharmacy Bills</li>
                </ol>
            </section>

            <section class="content">
                <?php echo isset($message) ? $message : ''; ?>

                <!-- Summary Cards -->
                <div class="row">
                    <div class="col-md-2">
                        <div class="small-box bg-red">
                            <div class="inner"><h3><?php echo isset($summary['total_pending']) ? (int)$summary['total_pending'] : 0; ?></h3><p>Awaiting Payment</p></div>
                            <div class="icon"><i class="fa fa-clock-o"></i></div>
                            <a href="<?php echo base_url(); ?>app/billing/pharmacy_bills?status=PENDING" class="small-box-footer">View <i class="fa fa-arrow-circle-right"></i></a>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="small-box bg-yellow">
                            <div class="inner"><h3><?php echo isset($summary['pending_today']) ? (int)$summary['pending_today'] : 0; ?></h3><p>New Today</p></div>
                            <div class="icon"><i class="fa fa-calendar"></i></div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="small-box bg-green">
                            <div class="inner"><h3><?php echo isset($summary['paid_today']) ? (int)$summary['paid_today'] : 0; ?></h3><p>Paid Today</p></div>
                            <div class="icon"><i class="fa fa-check-circle"></i></div>
                            <a href="<?php echo base_url(); ?>app/billing/pharmacy_bills?status=PAID" class="small-box-footer">View <i class="fa fa-arrow-circle-right"></i></a>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="small-box bg-purple">
                            <div class="inner"><h3><?php echo isset($summary['deferred']) ? (int)$summary['deferred'] : 0; ?></h3><p>Deferred</p></div>
                            <div class="icon"><i class="fa fa-calendar-times-o"></i></div>
                            <a href="<?php echo base_url(); ?>app/billing/pharmacy_bills?status=DEFERRED" class="small-box-footer">View <i class="fa fa-arrow-circle-right"></i></a>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="small-box bg-teal">
                            <div class="inner"><h3><?php echo isset($summary['external']) ? (int)$summary['external'] : 0; ?></h3><p>External</p></div>
                            <div class="icon"><i class="fa fa-external-link"></i></div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="small-box bg-maroon">
                            <div class="inner"><h3><?php echo isset($summary['pending_waivers']) ? (int)$summary['pending_waivers'] : 0; ?></h3><p>Waiver Requests</p></div>
                            <div class="icon"><i class="fa fa-gift"></i></div>
                            <a href="<?php echo base_url(); ?>app/billing/pharmacy_bills?status=WAIVED" class="small-box-footer">Manage <i class="fa fa-arrow-circle-right"></i></a>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="box box-default">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-filter"></i> Filter Bills</h3>
                    </div>
                    <div class="box-body">
                        <form method="get" action="<?php echo base_url(); ?>app/billing/pharmacy_bills">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Status</label>
                                        <select name="status" class="form-control input-sm">
                                            <option value="PENDING" <?php echo (isset($filters['status']) && $filters['status'] === 'PENDING') ? 'selected' : ''; ?>>Pending (Awaiting Payment)</option>
                                            <option value="PAID"    <?php echo (isset($filters['status']) && $filters['status'] === 'PAID')    ? 'selected' : ''; ?>>Paid</option>
                                            <option value="CANCELLED" <?php echo (isset($filters['status']) && $filters['status'] === 'CANCELLED') ? 'selected' : ''; ?>>Cancelled</option>
                                            <option value="DEFERRED" <?php echo (isset($filters['status']) && $filters['status'] === 'DEFERRED') ? 'selected' : ''; ?>>Deferred</option>
                                            <option value="UNABLE_TO_PAY" <?php echo (isset($filters['status']) && $filters['status'] === 'UNABLE_TO_PAY') ? 'selected' : ''; ?>>Unable to Pay</option>
                                            <option value="EXTERNAL" <?php echo (isset($filters['status']) && $filters['status'] === 'EXTERNAL') ? 'selected' : ''; ?>>External Purchase</option>
                                            <option value="WAIVED" <?php echo (isset($filters['status']) && $filters['status'] === 'WAIVED') ? 'selected' : ''; ?>>Waived</option>
                                            <option value="EMERGENCY" <?php echo (isset($filters['status']) && $filters['status'] === 'EMERGENCY') ? 'selected' : ''; ?>>Emergency</option>
                                            <option value="ALL"    <?php echo (isset($filters['status']) && $filters['status'] === 'ALL')    ? 'selected' : ''; ?>>All</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label>Date From</label>
                                        <input type="date" name="date_from" class="form-control input-sm" value="<?php echo htmlspecialchars(isset($filters['date_from']) ? $filters['date_from'] : ''); ?>">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label>Date To</label>
                                        <input type="date" name="date_to" class="form-control input-sm" value="<?php echo htmlspecialchars(isset($filters['date_to']) ? $filters['date_to'] : ''); ?>">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Search</label>
                                        <input type="text" name="search" class="form-control input-sm" value="<?php echo htmlspecialchars(isset($filters['search']) ? $filters['search'] : ''); ?>" placeholder="Patient / OPD / Drug">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group" style="margin-top:25px;">
                                        <button class="btn btn-primary btn-sm" type="submit"><i class="fa fa-search"></i> Filter</button>
                                        <a href="<?php echo base_url(); ?>app/billing/pharmacy_bills" class="btn btn-default btn-sm"><i class="fa fa-refresh"></i></a>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Bills Table -->
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-list"></i> Pharmacy Bills
                            <?php if (isset($filters['status']) && $filters['status'] !== 'ALL'): ?>
                                — <span class="text-<?php echo $filters['status'] === 'PAID' ? 'success' : ($filters['status'] === 'CANCELLED' ? 'muted' : 'danger'); ?>"><?php echo htmlspecialchars($filters['status']); ?></span>
                            <?php endif; ?>
                        </h3>
                    </div>
                    <div class="box-body table-responsive">
                        <table class="table table-bordered table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>OPD No.</th>
                                    <th>Patient</th>
                                    <th>Drug</th>
                                    <th>Qty</th>
                                    <th>Unit Price</th>
                                    <th>Total (GH₵)</th>
                                    <th>Payment</th>
                                    <th>Dispense</th>
                                    <th>Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (!isset($bills) || !is_array($bills) || count($bills) === 0): ?>
                                <tr><td colspan="11" class="text-center text-muted"><em>No pharmacy bills found for selected filters.</em></td></tr>
                            <?php else: ?>
                                <?php
                                $grandTotal = 0;
                                foreach ($bills as $bill):
                                    $payStatus = strtoupper(trim((string)(isset($bill->payment_status) ? $bill->payment_status : 'PENDING')));
                                    $dispStatus = strtoupper(trim((string)(isset($bill->dispense_status) ? $bill->dispense_status : 'WAITING')));
                                    $total = isset($bill->total) ? (float)$bill->total : 0;
                                    $grandTotal += $total;
                                    $payBadge = 'badge-pending';
                                    if ($payStatus === 'PAID') $payBadge = 'badge-paid';
                                    elseif ($payStatus === 'CANCELLED') $payBadge = 'badge-cancelled';
                                    $dispBadge = ($dispStatus === 'READY') ? 'badge-ready' : (($dispStatus === 'WAITING') ? 'badge-waiting' : 'badge-cancelled');
                                    $billId = isset($bill->id) ? (int)$bill->id : 0;
                                    $returnUrl = base_url() . 'app/billing/pharmacy_bills?' . http_build_query(isset($filters) ? $filters : array());
                                ?>
                                <tr>
                                    <td><?php echo $billId; ?></td>
                                    <td><strong><?php echo htmlspecialchars(isset($bill->iop_id) ? $bill->iop_id : ''); ?></strong></td>
                                    <td>
                                        <?php echo htmlspecialchars(isset($bill->patient_name) ? trim((string)$bill->patient_name) : ''); ?>
                                        <?php if (isset($bill->patient_no) && $bill->patient_no !== ''): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($bill->patient_no); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars(isset($bill->drug_name) ? $bill->drug_name : ''); ?></td>
                                    <td><?php echo htmlspecialchars(isset($bill->quantity) ? (float)$bill->quantity : 0); ?></td>
                                    <td><?php echo number_format(isset($bill->unit_price) ? (float)$bill->unit_price : 0, 2); ?></td>
                                    <td class="total-amount"><?php echo number_format($total, 2); ?></td>
                                    <td><span class="label <?php echo $payBadge; ?> badge-status"><?php echo $payStatus; ?></span></td>
                                    <td><span class="label <?php echo $dispBadge; ?> badge-status"><?php echo $dispStatus; ?></span></td>
                                    <td>
                                        <?php echo htmlspecialchars(isset($bill->created_at) ? date('d/m/Y H:i', strtotime($bill->created_at)) : ''); ?>
                                        <?php if ($payStatus === 'PAID' && isset($bill->paid_at) && $bill->paid_at): ?>
                                            <br><small class="text-success"><i class="fa fa-check"></i> Paid <?php echo date('d/m H:i', strtotime($bill->paid_at)); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($payStatus === 'PENDING'): ?>
                                        <form method="post" action="<?php echo base_url(); ?>app/billing/pharmacy_payment" style="display:inline-block;">
                                            <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                                            <input type="hidden" name="bill_id" value="<?php echo $billId; ?>">
                                            <input type="hidden" name="action" value="pay">
                                            <input type="hidden" name="return_url" value="<?php echo htmlspecialchars($returnUrl); ?>">
                                            <button type="submit" class="btn btn-success btn-xs" onclick="return confirm('Confirm payment received for <?php echo htmlspecialchars(addslashes(isset($bill->drug_name) ? $bill->drug_name : '')); ?> — GH₵ <?php echo number_format($total, 2); ?>?');">
                                                <i class="fa fa-money"></i> Receive Payment
                                            </button>
                                        </form>

                                        <button type="button" class="btn btn-danger btn-xs" data-toggle="modal" data-target="#cancelModal<?php echo $billId; ?>">
                                            <i class="fa fa-times"></i> Cancel
                                        </button>

                                        <!-- Cancel Modal -->
                                        <div class="modal fade" id="cancelModal<?php echo $billId; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <form method="post" action="<?php echo base_url(); ?>app/billing/pharmacy_payment">
                                                    <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                                                        <div class="modal-header">
                                                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                                                            <h4 class="modal-title">Cancel Pharmacy Bill</h4>
                                                        </div>
                                                        <div class="modal-body">
                                                            <input type="hidden" name="bill_id" value="<?php echo $billId; ?>">
                                                            <input type="hidden" name="action" value="cancel">
                                                            <input type="hidden" name="return_url" value="<?php echo htmlspecialchars($returnUrl); ?>">
                                                            <p class="text-danger"><i class="fa fa-warning"></i> Cancelling this bill means the medication will NOT be dispensed.</p>
                                                            <div class="form-group">
                                                                <label>Reason for Cancellation</label>
                                                                <textarea name="reason" class="form-control" rows="3" placeholder="e.g. Patient refused, drug unavailable..."></textarea>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                                                            <button type="submit" class="btn btn-danger">Confirm Cancel</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>

                                        <?php elseif ($payStatus === 'PAID'): ?>
                                            <span class="text-success"><i class="fa fa-check-circle"></i> Paid</span>
                                        <?php else: ?>
                                            <span class="text-muted"><i class="fa fa-ban"></i> Cancelled</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <tr class="info">
                                    <td colspan="6" class="text-right"><strong>Grand Total:</strong></td>
                                    <td class="total-amount">GH₵ <?php echo number_format($grandTotal, 2); ?></td>
                                    <td colspan="4"></td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Deferred Bills Section -->
                <?php if (isset($deferred_bills) && is_array($deferred_bills) && count($deferred_bills) > 0): ?>
                <div class="box box-warning">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-calendar-times-o"></i> Deferred &amp; Unable to Pay Bills</h3>
                        <span class="badge bg-red pull-right"><?php echo count($deferred_bills); ?></span>
                    </div>
                    <div class="box-body table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead><tr><th>#</th><th>OPD</th><th>Patient</th><th>Drug</th><th>Total (GH&#8373;)</th><th>Status</th><th>Defer Until</th><th>Notes</th><th>Action</th></tr></thead>
                            <tbody>
                            <?php foreach ($deferred_bills as $db):
                                $dbId = isset($db->id) ? (int)$db->id : 0;
                                $dbTotal = isset($db->total) ? (float)$db->total : 0;
                                $dbStatus = strtoupper(trim((string)(isset($db->payment_status) ? $db->payment_status : '')));
                                $dbExtStatus = strtoupper(trim((string)(isset($db->extended_status) ? $db->extended_status : '')));
                                $displayStatus = ($dbExtStatus !== '') ? $dbExtStatus : $dbStatus;
                            ?>
                            <tr>
                                <td><?php echo $dbId; ?></td>
                                <td><?php echo htmlspecialchars(isset($db->iop_id) ? $db->iop_id : ''); ?></td>
                                <td><?php echo htmlspecialchars(isset($db->patient_name) ? $db->patient_name : ''); ?><br><small class="text-muted"><?php echo htmlspecialchars(isset($db->patient_no) ? $db->patient_no : ''); ?></small></td>
                                <td><?php echo htmlspecialchars(isset($db->drug_name) ? $db->drug_name : ''); ?></td>
                                <td><strong>GH&#8373; <?php echo number_format($dbTotal, 2); ?></strong></td>
                                <td>
                                    <?php if ($displayStatus === 'UNABLE_TO_PAY'): ?><span class="label label-warning"><i class="fa fa-exclamation-triangle"></i> Unable to Pay</span>
                                    <?php elseif ($displayStatus === 'DEFERRED'): ?><span class="label label-warning"><i class="fa fa-calendar"></i> Deferred</span>
                                    <?php else: ?><span class="label label-default"><?php echo htmlspecialchars($displayStatus); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars(isset($db->defer_until) && $db->defer_until && $db->defer_until !== '0000-00-00' ? date('d/m/Y', strtotime($db->defer_until)) : '—'); ?></td>
                                <td><small><?php echo htmlspecialchars(isset($db->flex_notes) ? $db->flex_notes : ''); ?></small></td>
                                <td>
                                    <form method="post" action="<?php echo base_url(); ?>app/billing/pharmacy_payment" style="display:inline-block;">
                                        <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                                        <input type="hidden" name="bill_id" value="<?php echo $dbId; ?>">
                                        <input type="hidden" name="action" value="pay">
                                        <input type="hidden" name="return_url" value="<?php echo htmlspecialchars(base_url().'app/billing/pharmacy_bills?status=DEFERRED'); ?>">
                                        <button type="submit" class="btn btn-success btn-xs" onclick="return confirm('Confirm payment received? GH&#8373; <?php echo number_format($dbTotal, 2); ?>')"><i class="fa fa-money"></i> Settle</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Outstanding Balances Section -->
                <?php if (isset($outstanding) && is_array($outstanding) && count($outstanding) > 0): ?>
                <div class="box box-danger">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-exclamation-circle"></i> Outstanding Balances (Pharmacy)</h3>
                        <span class="badge bg-red pull-right"><?php echo count($outstanding); ?></span>
                    </div>
                    <div class="box-body table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead><tr><th>#</th><th>Patient</th><th>OPD</th><th>Amount (GH&#8373;)</th><th>Reason</th><th>Recorded</th><?php if (isset($is_admin) && $is_admin): ?><th>Admin Action</th><?php endif; ?></tr></thead>
                            <tbody>
                            <?php foreach ($outstanding as $ob):
                                $obId = isset($ob->id) ? (int)$ob->id : 0;
                                $obAmt = isset($ob->amount) ? (float)$ob->amount : 0;
                            ?>
                            <tr>
                                <td><?php echo $obId; ?></td>
                                <td><?php echo htmlspecialchars(isset($ob->patient_no) ? $ob->patient_no : ''); ?></td>
                                <td><?php echo htmlspecialchars(isset($ob->iop_id) ? $ob->iop_id : ''); ?></td>
                                <td><strong>GH&#8373; <?php echo number_format($obAmt, 2); ?></strong></td>
                                <td><small><?php echo htmlspecialchars(isset($ob->reason) ? $ob->reason : ''); ?></small></td>
                                <td><?php echo htmlspecialchars(isset($ob->created_at) ? date('d/m/Y', strtotime($ob->created_at)) : ''); ?></td>
                                <?php if (isset($is_admin) && $is_admin): ?>
                                <td>
                                    <form method="post" action="<?php echo base_url(); ?>app/billing/settle_outstanding" style="display:inline-block;">
                                        <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                                        <input type="hidden" name="outstanding_id" value="<?php echo $obId; ?>">
                                        <button type="submit" class="btn btn-success btn-xs" onclick="return confirm('Mark this outstanding balance as settled?')"><i class="fa fa-check"></i> Settle</button>
                                    </form>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Admin Waiver Approval Section -->
                <?php if (isset($is_admin) && $is_admin && isset($waiver_requests) && is_array($waiver_requests) && count($waiver_requests) > 0): ?>
                <div class="box box-primary">
                    <div class="box-header with-border" style="background-color:#337ab7;color:#fff;">
                        <h3 class="box-title"><i class="fa fa-gift"></i> Pending Waiver Requests — Admin Action Required</h3>
                        <span class="badge bg-yellow pull-right"><?php echo count($waiver_requests); ?></span>
                    </div>
                    <div class="box-body table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead><tr><th>#</th><th>Patient</th><th>Drug / Bill</th><th>Amount (GH&#8373;)</th><th>Reason</th><th>Requested By</th><th>Date</th><th>Admin Action</th></tr></thead>
                            <tbody>
                            <?php foreach ($waiver_requests as $wr):
                                $wrId = isset($wr->id) ? (int)$wr->id : 0;
                                $wrAmt = isset($wr->amount) ? (float)$wr->amount : 0;
                            ?>
                            <tr>
                                <td><?php echo $wrId; ?></td>
                                <td><?php echo htmlspecialchars(isset($wr->patient_no) ? $wr->patient_no : ''); ?></td>
                                <td><?php echo htmlspecialchars(isset($wr->drug_name) ? $wr->drug_name : (isset($wr->reference_id) ? '#'.$wr->reference_id : '—')); ?></td>
                                <td><strong>GH&#8373; <?php echo number_format($wrAmt, 2); ?></strong></td>
                                <td><small><?php echo htmlspecialchars(isset($wr->reason) ? $wr->reason : ''); ?></small></td>
                                <td><?php echo htmlspecialchars(isset($wr->requested_by) ? $wr->requested_by : ''); ?></td>
                                <td><?php echo htmlspecialchars(isset($wr->created_at) ? date('d/m/Y H:i', strtotime($wr->created_at)) : ''); ?></td>
                                <td>
                                    <button type="button" class="btn btn-success btn-xs" data-toggle="modal" data-target="#approveWaiverModal<?php echo $wrId; ?>"><i class="fa fa-check"></i> Approve</button>
                                    <button type="button" class="btn btn-danger btn-xs" data-toggle="modal" data-target="#rejectWaiverModal<?php echo $wrId; ?>"><i class="fa fa-times"></i> Reject</button>

                                    <!-- Approve Modal -->
                                    <div class="modal fade" id="approveWaiverModal<?php echo $wrId; ?>" tabindex="-1">
                                        <div class="modal-dialog"><div class="modal-content">
                                            <form method="post" action="<?php echo base_url(); ?>app/billing/approve_waiver">
                                                <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                                                <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title"><i class="fa fa-gift"></i> Approve Waiver</h4></div>
                                                <div class="modal-body">
                                                    <input type="hidden" name="waiver_id" value="<?php echo $wrId; ?>">
                                                    <input type="hidden" name="action" value="approve">
                                                    <p>Approving waiver for: <strong><?php echo htmlspecialchars(isset($wr->patient_no) ? $wr->patient_no : ''); ?></strong> — GH&#8373; <?php echo number_format($wrAmt, 2); ?></p>
                                                    <p class="text-muted">Reason: <?php echo htmlspecialchars(isset($wr->reason) ? $wr->reason : ''); ?></p>
                                                    <div class="form-group"><label>Approval Notes</label><textarea class="form-control" name="approval_notes" rows="2" placeholder="Admin approval notes..."></textarea></div>
                                                </div>
                                                <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button><button type="submit" class="btn btn-success"><i class="fa fa-check"></i> Approve Waiver</button></div>
                                            </form>
                                        </div></div>
                                    </div>

                                    <!-- Reject Modal -->
                                    <div class="modal fade" id="rejectWaiverModal<?php echo $wrId; ?>" tabindex="-1">
                                        <div class="modal-dialog"><div class="modal-content">
                                            <form method="post" action="<?php echo base_url(); ?>app/billing/approve_waiver">
                                                <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                                                <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title text-danger"><i class="fa fa-times"></i> Reject Waiver</h4></div>
                                                <div class="modal-body">
                                                    <input type="hidden" name="waiver_id" value="<?php echo $wrId; ?>">
                                                    <input type="hidden" name="action" value="reject">
                                                    <div class="form-group"><label>Rejection Reason</label><textarea class="form-control" name="approval_notes" rows="2" placeholder="Reason for rejection..."></textarea></div>
                                                </div>
                                                <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button><button type="submit" class="btn btn-danger">Reject Waiver</button></div>
                                            </form>
                                        </div></div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

            </section>
        </aside>
    </div>

    <script src="<?php echo base_url(); ?>public/js/jquery.min.js"></script>
    <script src="<?php echo base_url(); ?>public/js/bootstrap.min.js"></script>
    <script src="<?php echo base_url(); ?>public/js/AdminLTE/app.js"></script>
</body>
</html>
