<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Service Queue - Hebrew Medical Center</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <link href="<?php echo base_url() ?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/css/datatables/dataTables.bootstrap.css" rel="stylesheet" type="text/css" />
    <style>
        .summary-box { border-radius: 5px; padding: 15px; margin-bottom: 15px; color: #fff; }
        .summary-box h3 { margin: 0 0 5px 0; font-size: 28px; }
        .summary-box p { margin: 0; font-size: 13px; }
        .bg-lab { background: #3c8dbc; }
        .bg-sono { background: #00a65a; }
        .bg-proc { background: #f39c12; }
        .bg-total { background: #dd4b39; }
        .coverage-badge { font-size: 11px; padding: 3px 8px; border-radius: 3px; }
        .badge-nhis { background: #00a65a; color: #fff; }
        .badge-insurance { background: #3c8dbc; color: #fff; }
        .badge-company { background: #605ca8; color: #fff; }
        .badge-cash { background: #f39c12; color: #fff; }
    </style>
</head>
<body class="skin-blue">
    <?php require_once(APPPATH . 'views/include/header.php'); ?>

    <div class="wrapper row-offcanvas row-offcanvas-left">
        <?php require_once(APPPATH . 'views/include/sidebar.php'); ?>

        <aside class="right-side">
            <section class="content-header">
                <h1><i class="fa fa-list-alt"></i> Service Queue <small>Pending Services for Billing</small></h1>
                <ol class="breadcrumb">
                    <li><a href="<?php echo base_url() ?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                    <li><a href="#">Billing</a></li>
                    <li class="active">Service Queue</li>
                </ol>
            </section>

            <section class="content">
                <?php if ($this->session->flashdata('message')) { echo $this->session->flashdata('message'); } ?>

                <!-- Summary Boxes -->
                <div class="row">
                    <div class="col-md-3">
                        <div class="summary-box bg-lab">
                            <h3><?php echo isset($summary['lab_pending']) ? $summary['lab_pending'] : 0; ?></h3>
                            <p><i class="fa fa-flask"></i> Lab Tests Pending</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="summary-box bg-sono">
                            <h3><?php echo isset($summary['sonography_pending']) ? $summary['sonography_pending'] : 0; ?></h3>
                            <p><i class="fa fa-video-camera"></i> Sonography Pending</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="summary-box bg-proc">
                            <h3><?php echo isset($summary['procedure_pending']) ? $summary['procedure_pending'] : 0; ?></h3>
                            <p><i class="fa fa-stethoscope"></i> Procedures Pending</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="summary-box bg-total">
                            <h3>GHS <?php echo number_format(isset($summary['total_amount']) ? $summary['total_amount'] : 0, 2); ?></h3>
                            <p><i class="fa fa-money"></i> Total Pending Amount</p>
                        </div>
                    </div>
                </div>

                <!-- Coverage Summary -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="box box-info">
                            <div class="box-header with-border">
                                <h3 class="box-title">Coverage Breakdown</h3>
                            </div>
                            <div class="box-body">
                                <span class="badge badge-cash" style="margin-right:10px;"><i class="fa fa-money"></i> Cash: <?php echo isset($summary['cash_count']) ? $summary['cash_count'] : 0; ?></span>
                                <span class="badge badge-nhis" style="margin-right:10px;"><i class="fa fa-shield"></i> NHIS: <?php echo isset($summary['nhis_count']) ? $summary['nhis_count'] : 0; ?></span>
                                <span class="badge badge-insurance" style="margin-right:10px;"><i class="fa fa-building"></i> Insurance: <?php echo isset($summary['insurance_count']) ? $summary['insurance_count'] : 0; ?></span>
                                <span class="badge badge-company"><i class="fa fa-briefcase"></i> Company: <?php echo isset($summary['company_count']) ? $summary['company_count'] : 0; ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pending Orders Table -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="box box-primary">
                            <div class="box-header with-border">
                                <h3 class="box-title"><i class="fa fa-clock-o"></i> Pending Service Orders</h3>
                                <div class="box-tools pull-right">
                                    <a href="<?php echo base_url(); ?>app/service_queue/reports" class="btn btn-sm btn-info"><i class="fa fa-bar-chart"></i> Reports</a>
                                    <?php if (has_role('admin')) { ?>
                                    <a href="<?php echo base_url(); ?>app/service_queue/pending_approvals" class="btn btn-sm btn-warning"><i class="fa fa-gavel"></i> Approvals</a>
                                    <a href="<?php echo base_url(); ?>app/service_queue/companies" class="btn btn-sm btn-default"><i class="fa fa-building"></i> Companies</a>
                                    <?php } ?>
                                </div>
                            </div>
                            <div class="box-body table-responsive">
                                <table id="tblQueue" class="table table-bordered table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Order #</th>
                                            <th>Patient</th>
                                            <th>Service</th>
                                            <th>Type</th>
                                            <th>Coverage</th>
                                            <th>Amount</th>
                                            <th>Patient Pays</th>
                                            <th>Requested</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (isset($pending_orders) && count($pending_orders) > 0) { ?>
                                            <?php foreach ($pending_orders as $order) { ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($order->order_no); ?></strong></td>
                                                <td>
                                                    <a href="<?php echo base_url(); ?>app/patient/view/<?php echo $order->patient_no; ?>">
                                                        <?php echo htmlspecialchars(trim($order->firstname . ' ' . $order->lastname)); ?>
                                                    </a>
                                                    <br><small class="text-muted"><?php echo $order->patient_no; ?></small>
                                                </td>
                                                <td><?php echo htmlspecialchars($order->service_name); ?></td>
                                                <td>
                                                    <?php
                                                    $typeIcon = 'fa-question';
                                                    $typeClass = 'label-default';
                                                    switch (strtoupper($order->service_type)) {
                                                        case 'LAB':
                                                        case 'LABORATORY':
                                                            $typeIcon = 'fa-flask';
                                                            $typeClass = 'label-primary';
                                                            break;
                                                        case 'SONOGRAPHY':
                                                        case 'RADIOLOGY':
                                                            $typeIcon = 'fa-video-camera';
                                                            $typeClass = 'label-success';
                                                            break;
                                                        case 'PROCEDURE':
                                                            $typeIcon = 'fa-stethoscope';
                                                            $typeClass = 'label-warning';
                                                            break;
                                                        case 'MEDICATION':
                                                            $typeIcon = 'fa-medkit';
                                                            $typeClass = 'label-info';
                                                            break;
                                                    }
                                                    ?>
                                                    <span class="label <?php echo $typeClass; ?>"><i class="fa <?php echo $typeIcon; ?>"></i> <?php echo $order->service_type; ?></span>
                                                </td>
                                                <td>
                                                    <?php
                                                    $covClass = 'badge-cash';
                                                    switch (strtoupper($order->coverage_type)) {
                                                        case 'NHIS': $covClass = 'badge-nhis'; break;
                                                        case 'INSURANCE': $covClass = 'badge-insurance'; break;
                                                        case 'COMPANY': $covClass = 'badge-company'; break;
                                                    }
                                                    ?>
                                                    <span class="coverage-badge <?php echo $covClass; ?>"><?php echo $order->coverage_type; ?></span>
                                                    <?php if ((float)$order->coverage_percent > 0) { ?>
                                                        <br><small><?php echo $order->coverage_percent; ?>% covered</small>
                                                    <?php } ?>
                                                </td>
                                                <td class="text-right">GHS <?php echo number_format($order->final_price, 2); ?></td>
                                                <td class="text-right">
                                                    <strong>GHS <?php echo number_format($order->patient_amount, 2); ?></strong>
                                                    <?php if ((float)$order->covered_amount > 0) { ?>
                                                        <br><small class="text-success">Covered: <?php echo number_format($order->covered_amount, 2); ?></small>
                                                    <?php } ?>
                                                </td>
                                                <td><?php echo date('M d, H:i', strtotime($order->created_at)); ?></td>
                                                <td>
                                                    <?php if ((float)$order->patient_amount > 0) { ?>
                                                        <button class="btn btn-xs btn-success btn-pay" data-id="<?php echo $order->id; ?>" data-amount="<?php echo $order->patient_amount; ?>" data-patient="<?php echo htmlspecialchars(trim($order->firstname . ' ' . $order->lastname)); ?>">
                                                            <i class="fa fa-check"></i> Pay
                                                        </button>
                                                    <?php } else { ?>
                                                        <button class="btn btn-xs btn-info btn-confirm-covered" data-id="<?php echo $order->id; ?>">
                                                            <i class="fa fa-shield"></i> Confirm
                                                        </button>
                                                    <?php } ?>
                                                    <button class="btn btn-xs btn-warning btn-approval" data-id="<?php echo $order->id; ?>" data-patient="<?php echo $order->patient_no; ?>">
                                                        <i class="fa fa-gavel"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php } ?>
                                        <?php } else { ?>
                                            <tr><td colspan="9" class="text-center text-muted">No pending service orders</td></tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </aside>
    </div>

    <!-- Payment Modal -->
    <div class="modal fade" id="paymentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-green">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title"><i class="fa fa-money"></i> Process Payment</h4>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="pay_order_id">
                    <div class="form-group">
                        <label>Patient</label>
                        <p id="pay_patient" class="form-control-static"></p>
                    </div>
                    <div class="form-group">
                        <label>Amount</label>
                        <p id="pay_amount" class="form-control-static" style="font-size:24px; font-weight:bold;"></p>
                    </div>
                    <div class="form-group">
                        <label>Payment Method</label>
                        <select id="pay_method" class="form-control">
                            <option value="CASH">Cash</option>
                            <option value="MOMO">Mobile Money</option>
                            <option value="CARD">Card</option>
                            <option value="BANK">Bank Transfer</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Reference (optional)</label>
                        <input type="text" id="pay_reference" class="form-control" placeholder="Transaction reference">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="btnConfirmPayment"><i class="fa fa-check"></i> Confirm Payment</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Approval Request Modal -->
    <div class="modal fade" id="approvalModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-yellow">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title"><i class="fa fa-gavel"></i> Request Approval</h4>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="approval_order_id">
                    <div class="form-group">
                        <label>Approval Type</label>
                        <select id="approval_type" class="form-control">
                            <option value="WAIVE">Waive Payment</option>
                            <option value="DEFER">Defer Payment</option>
                            <option value="EMERGENCY">Emergency Approval</option>
                            <option value="CREDIT">Credit Approval</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Reason</label>
                        <textarea id="approval_reason" class="form-control" rows="3" placeholder="Enter reason for approval request..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-warning" id="btnSubmitApproval"><i class="fa fa-send"></i> Submit Request</button>
                </div>
            </div>
        </div>
    </div>

    <script src="<?php echo base_url(); ?>public/js/jquery.min.js"></script>
    <script src="<?php echo base_url(); ?>public/js/bootstrap.min.js"></script>
    <script src="<?php echo base_url(); ?>public/js/AdminLTE/app.js"></script>
    <script src="<?php echo base_url(); ?>public/js/datatables/jquery.dataTables.js"></script>
    <script src="<?php echo base_url(); ?>public/js/datatables/dataTables.bootstrap.js"></script>
    <script>
    var csrfName = '<?php echo $this->security->get_csrf_token_name(); ?>';
    var csrfHash = '<?php echo $this->security->get_csrf_hash(); ?>';
    $(function() {
        $('#tblQueue').DataTable({
            "pageLength": 25,
            "order": [[7, "asc"]]
        });

        // Payment button
        $('.btn-pay').click(function() {
            var id = $(this).data('id');
            var amount = $(this).data('amount');
            var patient = $(this).data('patient');
            $('#pay_order_id').val(id);
            $('#pay_amount').text('GHS ' + parseFloat(amount).toFixed(2));
            $('#pay_patient').text(patient);
            $('#paymentModal').modal('show');
        });

        // Confirm payment
        $('#btnConfirmPayment').click(function() {
            var btn = $(this);
            btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Processing...');
            
            var postData = {
                order_id: $('#pay_order_id').val(),
                payment_method: $('#pay_method').val(),
                reference: $('#pay_reference').val()
            };
            postData[csrfName] = csrfHash;
            $.post('<?php echo base_url(); ?>app/service_queue/process_payment', postData, function(resp) {
                if (resp.success) {
                    alert('Payment processed! Receipt: ' + resp.receipt_no);
                    location.reload();
                } else {
                    alert('Error: ' + resp.error);
                    btn.prop('disabled', false).html('<i class="fa fa-check"></i> Confirm Payment');
                }
            }, 'json').fail(function() {
                alert('Request failed');
                btn.prop('disabled', false).html('<i class="fa fa-check"></i> Confirm Payment');
            });
        });

        // Confirm covered (for NHIS/Insurance/Company)
        $('.btn-confirm-covered').click(function() {
            var id = $(this).data('id');
            if (confirm('Confirm this service is covered?')) {
                var coveredData = {
                    order_id: id,
                    payment_method: 'COVERED'
                };
                coveredData[csrfName] = csrfHash;
                $.post('<?php echo base_url(); ?>app/service_queue/process_payment', coveredData, function(resp) {
                    if (resp.success) {
                        alert('Service confirmed as covered');
                        location.reload();
                    } else {
                        alert('Error: ' + resp.error);
                    }
                }, 'json');
            }
        });

        // Approval request button
        $('.btn-approval').click(function() {
            $('#approval_order_id').val($(this).data('id'));
            $('#approvalModal').modal('show');
        });

        // Submit approval request
        $('#btnSubmitApproval').click(function() {
            var reason = $('#approval_reason').val().trim();
            if (!reason) {
                alert('Please enter a reason');
                return;
            }
            
            var btn = $(this);
            btn.prop('disabled', true);
            
            var approvalData = {
                order_id: $('#approval_order_id').val(),
                approval_type: $('#approval_type').val(),
                reason: reason
            };
            approvalData[csrfName] = csrfHash;
            $.post('<?php echo base_url(); ?>app/service_queue/request_approval', approvalData, function(resp) {
                if (resp.success) {
                    alert('Approval request submitted');
                    $('#approvalModal').modal('hide');
                } else {
                    alert('Error: ' + resp.error);
                }
                btn.prop('disabled', false);
            }, 'json').fail(function() {
                alert('Request failed');
                btn.prop('disabled', false);
            });
        });
    });
    </script>
</body>
</html>
