<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Hebrew Medical Center — Return Details</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
    <style>
        .detail-label { font-weight: bold; color: #666; }
        .detail-value { font-size: 14px; }
        .status-badge { padding: 5px 12px; border-radius: 3px; font-size: 12px; font-weight: bold; }
        .status-pending { background: #f39c12; color: #fff; }
        .status-approved { background: #00a65a; color: #fff; }
        .status-rejected { background: #dd4b39; color: #fff; }
        .audit-timeline { border-left: 3px solid #3c8dbc; padding-left: 20px; margin-left: 10px; }
        .audit-item { position: relative; padding-bottom: 15px; }
        .audit-item:before { content: ''; position: absolute; left: -26px; top: 5px; width: 12px; height: 12px; 
                             background: #3c8dbc; border-radius: 50%; border: 2px solid #fff; }
        .audit-item.create:before { background: #3c8dbc; }
        .audit-item.approve:before { background: #00a65a; }
        .audit-item.reject:before { background: #dd4b39; }
    </style>
</head>
<body class="skin-blue">
    <?php require_once(APPPATH.'views/include/header.php');?>
    <div class="wrapper row-offcanvas row-offcanvas-left">
        <?php require_once(APPPATH.'views/include/sidebar.php');?>
        <aside class="right-side">
            <section class="content-header">
                <h1>
                    <i class="fa fa-undo"></i> Return Details 
                    <small><?php echo htmlspecialchars($return->return_number ?? ''); ?></small>
                </h1>
                <ol class="breadcrumb">
                    <li><a href="<?php echo base_url()?>app/pharmacy"><i class="fa fa-medkit"></i> Pharmacy</a></li>
                    <li><a href="<?php echo base_url()?>app/pharmacy/pharmacy_returns">Returns</a></li>
                    <li class="active">View</li>
                </ol>
            </section>

            <section class="content">
                <?php if(isset($message) && $message){ echo $message; } ?>

                <?php if(!empty($return)): ?>
                <div class="row">
                    <!-- Return Details -->
                    <div class="col-md-8">
                        <div class="box box-primary">
                            <div class="box-header with-border">
                                <h3 class="box-title"><i class="fa fa-info-circle"></i> Return Information</h3>
                                <div class="box-tools pull-right">
                                    <?php
                                    $statusClass = 'status-pending';
                                    if ($return->status == 'APPROVED') $statusClass = 'status-approved';
                                    if ($return->status == 'REJECTED') $statusClass = 'status-rejected';
                                    ?>
                                    <span class="status-badge <?php echo $statusClass; ?>"><?php echo $return->status; ?></span>
                                </div>
                            </div>
                            <div class="box-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <table class="table table-condensed">
                                            <tr>
                                                <td class="detail-label">Return Number</td>
                                                <td class="detail-value"><strong><?php echo htmlspecialchars($return->return_number); ?></strong></td>
                                            </tr>
                                            <tr>
                                                <td class="detail-label">Return Date</td>
                                                <td class="detail-value"><?php echo date('d M Y', strtotime($return->return_date)); ?></td>
                                            </tr>
                                            <tr>
                                                <td class="detail-label">Patient</td>
                                                <td class="detail-value">
                                                    <?php echo htmlspecialchars($return->patient_name); ?><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($return->patient_no); ?></small>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="detail-label">Requested By</td>
                                                <td class="detail-value"><?php echo htmlspecialchars($return->requested_by_name ?? $return->requested_by); ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <table class="table table-condensed">
                                            <tr>
                                                <td class="detail-label">Drug</td>
                                                <td class="detail-value">
                                                    <strong><?php echo htmlspecialchars($return->drug_name); ?></strong>
                                                    <?php if(!empty($return->generic_name)): ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($return->generic_name); ?></small>
                                                    <?php endif; ?>
                                                    <?php if(!empty($return->strength)): ?>
                                                    <br><small><?php echo htmlspecialchars($return->strength); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="detail-label">Quantity Dispensed</td>
                                                <td class="detail-value"><?php echo number_format($return->quantity_dispensed, 0); ?></td>
                                            </tr>
                                            <tr>
                                                <td class="detail-label">Quantity Returned</td>
                                                <td class="detail-value"><strong class="text-success"><?php echo number_format($return->quantity_returned, 0); ?></strong></td>
                                            </tr>
                                            <tr>
                                                <td class="detail-label">Current Stock</td>
                                                <td class="detail-value"><?php echo number_format($return->current_stock ?? 0, 0); ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>

                                <hr>

                                <div class="row">
                                    <div class="col-md-6">
                                        <p class="detail-label">Return Reason</p>
                                        <p class="detail-value">
                                            <?php 
                                            $reasons = array(
                                                'OVER_DISPENSED' => 'Over-dispensed',
                                                'PATIENT_REFUSED' => 'Patient refused',
                                                'WRONG_DRUG' => 'Wrong drug dispensed',
                                                'EXPIRED_DRUG' => 'Expired drug',
                                                'DAMAGED_DRUG' => 'Damaged drug',
                                                'PRESCRIPTION_CANCELLED' => 'Prescription cancelled',
                                                'ADVERSE_REACTION' => 'Adverse reaction',
                                                'OTHER' => 'Other'
                                            );
                                            echo isset($reasons[$return->return_reason]) ? $reasons[$return->return_reason] : $return->return_reason;
                                            ?>
                                        </p>
                                    </div>
                                    <div class="col-md-6">
                                        <p class="detail-label">Notes</p>
                                        <p class="detail-value"><?php echo !empty($return->return_notes) ? nl2br(htmlspecialchars($return->return_notes)) : '<span class="text-muted">No notes</span>'; ?></p>
                                    </div>
                                </div>

                                <?php if($return->status == 'APPROVED'): ?>
                                <hr>
                                <div class="row">
                                    <div class="col-md-6">
                                        <p class="detail-label">Approved By</p>
                                        <p class="detail-value"><?php echo htmlspecialchars($return->approved_by_name ?? $return->approved_by); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p class="detail-label">Approved Date</p>
                                        <p class="detail-value"><?php echo date('d M Y H:i', strtotime($return->approved_date)); ?></p>
                                    </div>
                                </div>
                                <div class="alert alert-success">
                                    <i class="fa fa-check-circle"></i> Stock has been adjusted. 
                                    <strong><?php echo number_format($return->quantity_returned, 0); ?></strong> units added back to inventory.
                                </div>
                                <?php endif; ?>

                                <?php if($return->status == 'REJECTED'): ?>
                                <hr>
                                <div class="row">
                                    <div class="col-md-6">
                                        <p class="detail-label">Rejected By</p>
                                        <p class="detail-value"><?php echo htmlspecialchars($return->approved_by_name ?? $return->approved_by); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p class="detail-label">Rejected Date</p>
                                        <p class="detail-value"><?php echo date('d M Y H:i', strtotime($return->approved_date)); ?></p>
                                    </div>
                                </div>
                                <div class="alert alert-danger">
                                    <i class="fa fa-times-circle"></i> <strong>Rejection Reason:</strong><br>
                                    <?php echo nl2br(htmlspecialchars($return->rejection_reason)); ?>
                                </div>
                                <?php endif; ?>

                            </div>
                            <div class="box-footer">
                                <a href="<?php echo base_url(); ?>app/pharmacy/pharmacy_returns" class="btn btn-default">
                                    <i class="fa fa-arrow-left"></i> Back to List
                                </a>
                                <?php if($return->status == 'PENDING' && (has_role('admin') || has_role('pharmacist'))): ?>
                                <div class="pull-right">
                                    <button type="button" class="btn btn-success" data-toggle="modal" data-target="#approveModal">
                                        <i class="fa fa-check"></i> Approve
                                    </button>
                                    <button type="button" class="btn btn-danger" data-toggle="modal" data-target="#rejectModal">
                                        <i class="fa fa-times"></i> Reject
                                    </button>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Audit Trail -->
                    <div class="col-md-4">
                        <div class="box box-info">
                            <div class="box-header with-border">
                                <h3 class="box-title"><i class="fa fa-history"></i> Audit Trail</h3>
                            </div>
                            <div class="box-body">
                                <div class="audit-timeline">
                                    <?php if(!empty($audit_trail)): foreach($audit_trail as $audit): 
                                        $auditClass = 'create';
                                        if ($audit->action == 'APPROVE') $auditClass = 'approve';
                                        if ($audit->action == 'REJECT') $auditClass = 'reject';
                                    ?>
                                    <div class="audit-item <?php echo $auditClass; ?>">
                                        <strong><?php echo $audit->action; ?></strong><br>
                                        <small class="text-muted">
                                            <i class="fa fa-user"></i> <?php echo htmlspecialchars($audit->user_name ?? $audit->user_id); ?><br>
                                            <i class="fa fa-clock-o"></i> <?php echo date('d M Y H:i', strtotime($audit->created_at)); ?>
                                        </small>
                                        <?php if(!empty($audit->notes)): ?>
                                        <br><small><?php echo htmlspecialchars($audit->notes); ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <?php endforeach; else: ?>
                                    <p class="text-muted">No audit records</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Dispense Info -->
                        <div class="box box-default">
                            <div class="box-header with-border">
                                <h3 class="box-title"><i class="fa fa-pills"></i> Original Dispense</h3>
                            </div>
                            <div class="box-body">
                                <p><strong>Dispense Date:</strong><br>
                                <?php echo !empty($return->dispense_date) ? date('d M Y H:i', strtotime($return->dispense_date)) : 'N/A'; ?></p>
                                <?php if(!empty($return->batch_no)): ?>
                                <p><strong>Batch Number:</strong><br><?php echo htmlspecialchars($return->batch_no); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="alert alert-danger">
                    <i class="fa fa-exclamation-triangle"></i> Return not found.
                </div>
                <?php endif; ?>

            </section>
        </aside>
    </div>

    <!-- Approve Modal -->
    <div class="modal fade" id="approveModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" action="<?php echo base_url(); ?>app/pharmacy/approve_return">
                    <div class="modal-header bg-green">
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                        <h4 class="modal-title"><i class="fa fa-check"></i> Approve Return</h4>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="return_id" value="<?php echo $return->return_id ?? ''; ?>">
                        <p>Are you sure you want to approve this return?</p>
                        <p><strong>Drug:</strong> <?php echo htmlspecialchars($return->drug_name ?? ''); ?></p>
                        <p><strong>Quantity:</strong> <?php echo number_format($return->quantity_returned ?? 0, 0); ?> units</p>
                        <p class="text-success"><i class="fa fa-info-circle"></i> Stock will be increased by <?php echo number_format($return->quantity_returned ?? 0, 0); ?> units.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success"><i class="fa fa-check"></i> Approve</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reject Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" action="<?php echo base_url(); ?>app/pharmacy/reject_return">
                    <div class="modal-header bg-red">
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                        <h4 class="modal-title"><i class="fa fa-times"></i> Reject Return</h4>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="return_id" value="<?php echo $return->return_id ?? ''; ?>">
                        <div class="form-group">
                            <label>Rejection Reason <span class="text-danger">*</span></label>
                            <textarea name="rejection_reason" class="form-control" rows="3" required 
                                      placeholder="Enter reason for rejection..."></textarea>
                        </div>
                        <p class="text-warning"><i class="fa fa-warning"></i> Stock will NOT be adjusted.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger"><i class="fa fa-times"></i> Reject</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="<?php echo base_url();?>public/js/jquery.min.js"></script>
    <script src="<?php echo base_url();?>public/js/bootstrap.min.js"></script>
    <script src="<?php echo base_url();?>public/js/AdminLTE/app.js"></script>
</body>
</html>
