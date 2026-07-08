<?php
// Prevent browser caching to avoid stale flash messages
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo isset($companyInfo->site_title) ? $companyInfo->site_title : 'HMS'; ?> — Invoice Details</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
</head>
<body class="skin-blue">
    <?php require_once(APPPATH.'views/include/header.php');?>
    <div class="wrapper row-offcanvas row-offcanvas-left">
        <?php require_once(APPPATH.'views/include/sidebar.php');?>
        <aside class="right-side">
            <section class="content-header">
                <h1>Invoice Details <small><?php echo isset($invoice) ? $invoice->invoice_no : ''; ?></small></h1>
                <ol class="breadcrumb">
                    <li><a href="<?php echo base_url();?>app/cashier/payments"><i class="fa fa-arrow-left"></i> Back to Payments</a></li>
                </ol>
            </section>
            <section class="content">
                <?php echo isset($message) ? $message : ''; ?>

                <?php if (isset($invoice)) { ?>
                <div class="row">
                    <!-- Invoice Info -->
                    <div class="col-md-8">
                        <div class="box box-primary">
                            <div class="box-header with-border">
                                <h3 class="box-title"><i class="fa fa-file-text-o"></i> Invoice #<?php echo $invoice->invoice_no; ?></h3>
                                <div class="box-tools pull-right">
                                    <?php if (isset($payments) && count($payments) > 0) { ?>
                                        <a href="<?php echo base_url();?>app/cashier/thermal_final_receipt/<?php echo $invoice->invoice_no; ?>" class="btn btn-sm btn-success" target="_blank"><i class="fa fa-print"></i> Print Final Receipt (Thermal)</a>
                                    <?php } ?>
                                    <a href="<?php echo base_url();?>app/billing/billingpdf?invoice_no=<?php echo $invoice->invoice_no; ?>" class="btn btn-sm btn-default" target="_blank"><i class="fa fa-print"></i> Print Invoice PDF</a>
                                </div>
                            </div>
                            <div class="box-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>Patient:</strong> <?php echo $invoice->patient_name; ?></p>
                                        <p><strong>Patient ID:</strong> <?php echo $invoice->patient_no; ?></p>
                                        <p><strong>Phone:</strong> <?php echo isset($invoice->phone) ? $invoice->phone : '-'; ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Invoice Date:</strong> <?php echo date('Y-m-d H:i', strtotime($invoice->dDate)); ?></p>
                                        <p><strong>IOP ID:</strong> <?php echo $invoice->iop_id; ?></p>
                                    </div>
                                </div>
                                <hr>
                                <h4>Line Items</h4>
                                <table class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>Description</th>
                                            <th class="text-right">Qty</th>
                                            <th class="text-right">Rate</th>
                                            <th class="text-right">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (isset($items) && count($items) > 0) { ?>
                                            <?php foreach ($items as $item) { ?>
                                            <tr>
                                                <td><?php echo isset($item->bill_name) ? $item->bill_name : (isset($item->particular_name) ? $item->particular_name : '-'); ?></td>
                                                <td class="text-right"><?php echo isset($item->qty) ? $item->qty : 1; ?></td>
                                                <td class="text-right"><?php echo number_format(isset($item->rate) ? $item->rate : 0, 2); ?></td>
                                                <td class="text-right"><?php echo number_format(isset($item->amount) ? $item->amount : 0, 2); ?></td>
                                            </tr>
                                            <?php } ?>
                                        <?php } else { ?>
                                            <tr><td colspan="4" class="text-center text-muted">No items</td></tr>
                                        <?php } ?>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <th colspan="3" class="text-right">Subtotal:</th>
                                            <th class="text-right"><?php echo number_format($invoice->total_amount, 2); ?></th>
                                        </tr>
                                        <?php if ((float)$invoice->discount > 0) { ?>
                                        <tr>
                                            <th colspan="3" class="text-right">Discount:</th>
                                            <th class="text-right text-danger">-<?php echo number_format($invoice->discount, 2); ?></th>
                                        </tr>
                                        <?php } ?>
                                        <tr>
                                            <th colspan="3" class="text-right">Amount Paid:</th>
                                            <th class="text-right text-success"><?php echo number_format($invoice->amount_paid, 2); ?></th>
                                        </tr>
                                        <tr class="<?php echo (float)$invoice->balance > 0 ? 'danger' : 'success'; ?>">
                                            <th colspan="3" class="text-right">Balance Due:</th>
                                            <th class="text-right"><strong><?php echo number_format($invoice->balance, 2); ?></strong></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        <!-- Payment History -->
                        <div class="box box-success">
                            <div class="box-header with-border">
                                <h3 class="box-title"><i class="fa fa-history"></i> Payment History</h3>
                            </div>
                            <div class="box-body table-responsive no-padding">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Receipt #</th>
                                            <th>Date</th>
                                            <th>Method</th>
                                            <th class="text-right">Amount</th>
                                            <th>Cashier</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (isset($payments) && count($payments) > 0) { ?>
                                            <?php foreach ($payments as $pay) { ?>
                                            <tr class="<?php echo (isset($pay->voided) && $pay->voided) ? 'danger' : ''; ?>">
                                                <td>
                                                    <?php echo $pay->receipt_no; ?>
                                                    <?php if ((isset($pay->payment_method) && strtoupper(trim((string)$pay->payment_method)) === 'REFUND') && isset($pay->reference_no) && trim((string)$pay->reference_no) !== '') { ?>
                                                        <br><small class="text-muted">of #<?php echo $pay->reference_no; ?></small>
                                                    <?php } ?>
                                                </td>
                                                <td><?php echo date('Y-m-d H:i', strtotime($pay->payment_date)); ?></td>
                                                <td><?php echo $pay->payment_method; ?></td>
                                                <td class="text-right <?php echo ((float)$pay->amount < 0) ? 'text-danger' : ''; ?>"><?php echo number_format($pay->amount, 2); ?></td>
                                                <td><?php echo isset($pay->cashier_name) ? $pay->cashier_name : '-'; ?></td>
                                                <td>
                                                    <?php if (isset($pay->voided) && $pay->voided) { ?>
                                                        <span class="label label-danger">Voided</span>
                                                    <?php } elseif ((isset($pay->payment_method) && strtoupper(trim((string)$pay->payment_method)) === 'REFUND') || (isset($pay->amount) && (float)$pay->amount < 0)) { ?>
                                                        <span class="label label-warning">Refund</span>
                                                    <?php } else { ?>
                                                        <?php
                                                            $refunded_total = 0.0;
                                                            if (isset($refund_totals) && is_array($refund_totals) && isset($refund_totals[(string)$pay->receipt_no])) {
                                                                $refunded_total = (float)$refund_totals[(string)$pay->receipt_no];
                                                            }
                                                        ?>
                                                        <?php if ($refunded_total > 0.009) { ?>
                                                            <span class="label label-warning">Partially Refunded</span>
                                                            <span class="text-muted" style="margin-left:6px;">(GHS <?php echo number_format($refunded_total, 2); ?>)</span>
                                                        <?php } else { ?>
                                                            <span class="label label-success">Valid</span>
                                                        <?php } ?>
                                                    <?php } ?>
                                                </td>
                                                <td>
                                                    <a href="<?php echo base_url();?>app/cashier/print_receipt/<?php echo $pay->receipt_no; ?>" class="btn btn-xs btn-default" target="_blank" style="margin-right: 2px;"><i class="fa fa-print"></i> Thermal</a>
                                                    <a href="<?php echo base_url();?>app/cashier/pdf_receipt/<?php echo $pay->receipt_no; ?>" class="btn btn-xs btn-info" target="_blank" style="margin-right: 2px;"><i class="fa fa-file-text-o"></i> PDF</a>
                                                    <?php if (isset($can_refund) && $can_refund) { ?>
                                                        <?php
                                                            $isRefundRow = (isset($pay->payment_method) && strtoupper(trim((string)$pay->payment_method)) === 'REFUND') || ((float)$pay->amount < 0);
                                                            $isVoidedRow = (isset($pay->voided) && $pay->voided);
                                                            $refunded_total = 0.0;
                                                            if (isset($refund_totals) && is_array($refund_totals) && isset($refund_totals[(string)$pay->receipt_no])) {
                                                                $refunded_total = (float)$refund_totals[(string)$pay->receipt_no];
                                                            }
                                                            $remaining = max(0.0, (float)$pay->amount - (float)$refunded_total);
                                                            $canRefund = (!$isRefundRow && !$isVoidedRow && (float)$pay->amount > 0 && $remaining > 0.009);
                                                        ?>
                                                        <?php if ($canRefund) { ?>
                                                            <button type="button" class="btn btn-xs btn-warning btn-refund" data-receipt="<?php echo $pay->receipt_no; ?>" data-remaining="<?php echo number_format((float)$remaining, 2, '.', ''); ?>"><i class="fa fa-undo"></i> Refund</button>
                                                        <?php } ?>
                                                    <?php } ?>
                                                </td>
                                            </tr>
                                            <?php } ?>
                                        <?php } else { ?>
                                            <tr><td colspan="7" class="text-center text-muted">No payments recorded</td></tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <?php if (isset($refunds) && is_array($refunds) && count($refunds) > 0) { ?>
                        <div class="box box-warning">
                            <div class="box-header with-border">
                                <h3 class="box-title"><i class="fa fa-undo"></i> Refund History</h3>
                            </div>
                            <div class="box-body table-responsive no-padding">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Refund Receipt #</th>
                                            <th>Original Receipt #</th>
                                            <th>Date</th>
                                            <th class="text-right">Amount</th>
                                            <th>Refunded By</th>
                                            <th>Reason</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($refunds as $r) { ?>
                                        <tr>
                                            <td><?php echo isset($r->refund_receipt_no) ? $r->refund_receipt_no : '-'; ?></td>
                                            <td><?php echo isset($r->original_receipt_no) ? $r->original_receipt_no : '-'; ?></td>
                                            <td><?php echo isset($r->refunded_at) ? date('Y-m-d H:i', strtotime($r->refunded_at)) : '-'; ?></td>
                                            <td class="text-right text-danger"><?php echo number_format(isset($r->amount) ? (float)$r->amount : 0, 2); ?></td>
                                            <td><?php echo isset($r->refunded_by_name) ? $r->refunded_by_name : '-'; ?></td>
                                            <td><?php echo isset($r->reason) ? $r->reason : '-'; ?></td>
                                            <td>
                                                <?php if (isset($r->refund_receipt_no) && trim((string)$r->refund_receipt_no) !== '') { ?>
                                                    <a href="<?php echo base_url();?>app/cashier/print_receipt/<?php echo $r->refund_receipt_no; ?>" class="btn btn-xs btn-default" target="_blank"><i class="fa fa-print"></i></a>
                                                    <a href="<?php echo base_url();?>app/cashier/pdf_receipt/<?php echo $r->refund_receipt_no; ?>" class="btn btn-xs btn-info" target="_blank"><i class="fa fa-file-pdf-o"></i></a>
                                                <?php } ?>
                                            </td>
                                        </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php } ?>

                        <?php if (!empty($walkin_order)) { ?>
                        <div class="box box-info">
                            <div class="box-header with-border">
                                <h3 class="box-title"><i class="fa fa-check-square-o"></i> Walk-In Fulfillment</h3>
                            </div>
                            <div class="box-body">
                                <div class="row" style="margin-bottom:10px;">
                                    <div class="col-sm-4"><strong>Walk-In Code:</strong> <?php echo htmlspecialchars($walkin_order->walkin_code ?: $walkin_order->walkin_order_id); ?></div>
                                    <div class="col-sm-4"><strong>Client:</strong> <?php echo htmlspecialchars($walkin_order->customer_name ?: 'Walk-in Client'); ?></div>
                                    <div class="col-sm-4"><strong>Status:</strong> <span class="label label-primary"><?php echo htmlspecialchars($walkin_order->fulfillment_status); ?></span></div>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-condensed">
                                        <thead><tr><th>Department</th><th>Item</th><th class="text-right">Paid Qty</th><th class="text-right">Fulfilled</th><th class="text-right">Pending</th><th>Department Status</th></tr></thead>
                                        <tbody>
                                        <?php foreach ((array)$walkin_fulfillment_items as $wi) { ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($wi->department); ?></td>
                                                <td><?php echo htmlspecialchars($wi->item_name); ?></td>
                                                <td class="text-right"><?php echo number_format((float)$wi->quantity, 2); ?></td>
                                                <td class="text-right"><?php echo number_format((float)$wi->fulfilled_qty, 2); ?></td>
                                                <td class="text-right"><?php echo number_format((float)$wi->remaining_qty, 2); ?></td>
                                                <td><span class="label label-info"><?php echo htmlspecialchars($wi->department_status); ?></span></td>
                                            </tr>
                                        <?php } ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <?php } ?>
                        <!-- Service Dispatch Clearance Status -->
                        <div class="box box-warning">
                            <div class="box-header with-border">
                                <h3 class="box-title"><i class="fa fa-send"></i> Department Service Clearance / Dispatch Status</h3>
                            </div>
                            <div class="box-body">
                                <div id="dispatch-status-container">
                                    <p class="text-center text-muted">Loading dispatch status...</p>
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- Payment Panel -->
                    <div class="col-md-4">
                        <?php if ((float)$invoice->balance > 0) { ?>
                        <div class="box box-success">
                            <div class="box-header with-border bg-green">
                                <h3 class="box-title"><i class="fa fa-money"></i> Collect Payment</h3>
                            </div>
                            <form method="post" action="<?php echo base_url();?>app/cashier/process_payment">
                                <div class="box-body">
                                    <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                                    <input type="hidden" name="invoice_no" value="<?php echo $invoice->invoice_no; ?>">
                                    
                                    <div class="callout callout-info">
                                        <h4>Balance Due</h4>
                                        <p class="lead"><strong>GHS <?php echo number_format($invoice->balance, 2); ?></strong></p>
                                    </div>

                                    <div class="form-group">
                                        <label>Amount <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-addon">GHS</span>
                                            <input type="number" name="amount" class="form-control input-lg" step="0.01" min="0.01" max="<?php echo $invoice->balance; ?>" value="<?php echo number_format($invoice->balance, 2, '.', ''); ?>" required>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label>Payment Method <span class="text-danger">*</span></label>
                                        <select name="payment_method" id="payment_method" class="form-control" required>
                                            <?php if (isset($payment_methods)) { foreach ($payment_methods as $pm) { ?>
                                                <option value="<?php echo $pm->method_code; ?>" data-ref="<?php echo $pm->requires_reference; ?>"><?php echo $pm->method_name; ?></option>
                                            <?php } } ?>
                                        </select>
                                    </div>

                                    <div class="form-group" id="ref_group" style="display:none">
                                        <label>Reference #</label>
                                        <input type="text" name="reference" class="form-control" placeholder="Transaction ID">
                                    </div>

                                    <div class="form-group">
                                        <label>Notes</label>
                                        <textarea name="notes" class="form-control" rows="2"></textarea>
                                    </div>
                                </div>
                                <div class="box-footer">
                                    <button type="submit" class="btn btn-success btn-block btn-lg"><i class="fa fa-check"></i> Record Payment</button>
                                </div>
                            </form>
                        </div>
                        <?php } else { ?>
                        <div class="box box-success">
                            <div class="box-header with-border bg-green">
                                <h3 class="box-title"><i class="fa fa-check-circle"></i> Fully Paid</h3>
                            </div>
                            <div class="box-body text-center">
                                <i class="fa fa-check-circle fa-5x text-success"></i>
                                <h3 class="text-success">Invoice Paid in Full</h3>
                                <p>Total Paid: <strong>GHS <?php echo number_format($invoice->amount_paid, 2); ?></strong></p>
                            </div>
                        </div>
                        <?php } ?>
                    </div>
                </div>
                <?php } else { ?>
                <div class="alert alert-danger">Invoice not found.</div>
                <?php } ?>

            </section>
        </aside>
    </div>

    <script src="<?php echo base_url();?>public/js/jquery.min.js"></script>
    <script src="<?php echo base_url();?>public/js/bootstrap.min.js"></script>
    <script src="<?php echo base_url();?>public/js/AdminLTE/app.js"></script>
    <div class="modal fade" id="refundModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form method="post" action="<?php echo base_url();?>app/cashier/refund_payment" id="refundForm">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        <h4 class="modal-title">Issue Refund</h4>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                        <input type="hidden" name="receipt_no" id="refund_receipt_no_original" value="">
                        <input type="hidden" name="refund_receipt_no" id="refund_receipt_no" value="">
                        <div class="form-group">
                            <label>Refund Amount <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-addon">GHS</span>
                                <input type="number" name="refund_amount" id="refund_amount" class="form-control" step="0.01" min="0.01" required>
                            </div>
                            <p class="help-block" id="refund_max_help"></p>
                        </div>
                        <div class="form-group">
                            <label>Reason <span class="text-danger">*</span></label>
                            <textarea name="refund_reason" id="refund_reason" class="form-control" rows="3" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning"><i class="fa fa-undo"></i> Refund</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script>
    $(document).ready(function() {
        $('#payment_method').on('change', function() {
            var reqRef = $(this).find(':selected').data('ref');
            if (reqRef == 1) {
                $('#ref_group').show();
            } else {
                $('#ref_group').hide();
            }
        });

        $(document).on('click', '.btn-refund', function() {
            var receipt = $(this).data('receipt');
            var remaining = $(this).data('remaining');
            $('#refund_receipt_no_original').val(receipt);
            $('#refund_amount').val(remaining);
            $('#refund_amount').attr('max', remaining);
            $('#refund_reason').val('');
            $('#refund_max_help').text('Max refundable now: GHS ' + remaining);
            var key = 'RF' + (new Date().getTime().toString()) + Math.floor(Math.random() * 900 + 100).toString();
            $('#refund_receipt_no').val(key);
            $('#refundModal').modal('show');
        });

        function updateDispatchStatus() {
            $.getJSON('<?php echo base_url();?>app/cashier/dispatch_status_json/<?php echo $invoice->invoice_no; ?>', function(data) {
                var html = '';
                if (data && data.length > 0) {
                    html += '<div class="table-responsive"><table class="table table-striped table-condensed">';
                    html += '<thead><tr><th>Department</th><th>Items Billed</th><th>Status</th><th>Clearance Date</th></tr></thead><tbody>';
                    $.each(data, function(i, item) {
                        var statusLabel = '';
                        if (item.status === 'PENDING') {
                            statusLabel = '<span class="label label-warning"><i class="fa fa-clock-o"></i> PENDING PROCEED</span>';
                        } else if (item.status === 'DISPATCHED') {
                            statusLabel = '<span class="label label-success"><i class="fa fa-check"></i> CLEARED / PROCESSED</span>';
                        } else {
                            statusLabel = '<span class="label label-danger">' + item.status + '</span>';
                        }
                        
                        var dispatchInfo = '';
                        if (item.status === 'DISPATCHED' && item.dispatched_at) {
                            dispatchInfo = '<br><small class="text-muted">Cleared At: ' + item.dispatched_at + '</small>';
                        }

                        html += '<tr>';
                        html += '<td><strong>' + item.department + '</strong></td>';
                        html += '<td>' + item.item_details + '</td>';
                        html += '<td>' + statusLabel + dispatchInfo + '</td>';
                        html += '<td>' + item.created_at + '</td>';
                        html += '</tr>';
                    });
                    html += '</tbody></table></div>';
                } else {
                    html = '<p class="text-center text-muted">No departments cleared/notified for this invoice yet. Clearance notifications are triggered upon recording payment.</p>';
                }
                $('#dispatch-status-container').html(html);
            });
        }
        
        // Initial load and poll every 5 seconds
        updateDispatchStatus();
        setInterval(updateDispatchStatus, 5000);
    });
    </script>
</body>
</html>
