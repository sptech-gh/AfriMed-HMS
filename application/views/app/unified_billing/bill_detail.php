<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo $page_title; ?> | HMS</title>
    <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
    <link href="<?php echo base_url(); ?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css">
    <link href="<?php echo base_url(); ?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css">
    <link href="<?php echo base_url(); ?>public/css/AdminLTE.css" rel="stylesheet" type="text/css">
    <style>
        .bill-header { background: #f5f5f5; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
        .status-badge { padding: 8px 16px; border-radius: 20px; font-size: 14px; font-weight: bold; text-transform: uppercase; }
        .status-PAID { background: #00a65a; color: white; }
        .status-PENDING { background: #f39c12; color: white; }
        .status-PARTIAL { background: #f56954; color: white; }
        .status-CANCELLED { background: #999; color: white; }
        .amount-box { text-align: center; padding: 15px; border: 2px solid #ddd; border-radius: 8px; margin: 10px 0; }
        .amount-box h4 { margin: 0; font-size: 24px; font-weight: bold; }
        .amount-box.paid { border-color: #00a65a; color: #00a65a; }
        .amount-box.balance { border-color: #f56954; color: #f56954; }
        .item-row { padding: 10px; border-bottom: 1px solid #eee; }
        .item-row:last-child { border-bottom: none; }
        .gate-status { font-size: 11px; padding: 2px 8px; border-radius: 10px; }
        .gate-BLOCKED { background: #f56954; color: white; }
        .gate-RELEASED { background: #00a65a; color: white; }
    </style>
</head>
<body class="skin-blue">

<?php require_once(APPPATH.'views/include/header.php');?>
<?php require_once(APPPATH.'views/include/sidebar.php');?>

<div class="wrapper row-offcanvas row-offcanvas-left">
    <aside class="right-side">
        <section class="content-header">
            <h1>Bill Detail <small><?php echo $bill->bill_no; ?></small></h1>
            <ol class="breadcrumb">
                <li><a href="<?php echo base_url(); ?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                <li><a href="<?php echo base_url(); ?>app/unified_billing">Billing</a></li>
                <li class="active">Bill Detail</li>
            </ol>
        </section>

        <section class="content">
            <!-- Bill Header -->
            <div class="row">
                <div class="col-md-8">
                    <div class="bill-header">
                        <div class="row">
                            <div class="col-md-6">
                                <h3><strong><?php echo $bill->bill_no; ?></strong></h3>
                                <p class="text-muted">Created: <?php echo date('M d, Y H:i', strtotime($bill->created_at)); ?></p>
                                <p class="text-muted">By: <?php echo $bill->created_by; ?></p>
                            </div>
                            <div class="col-md-6 text-right">
                                <span class="status-badge status-<?php echo $bill->payment_status; ?>">
                                    <?php echo $bill->payment_status; ?>
                                </span>
                                <p class="text-muted" style="margin-top: 10px;">
                                    Visit: <?php echo $bill->visit_type; ?><br>
                                    <?php echo $bill->visit_id; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="box">
                        <div class="box-body">
                            <div class="amount-box">
                                <p>Total Amount</p>
                                <h4>GHS <?php echo number_format($bill->net_amount, 2); ?></h4>
                            </div>
                            <div class="amount-box paid">
                                <p>Amount Paid</p>
                                <h4>GHS <?php echo number_format($bill->paid_amount, 2); ?></h4>
                            </div>
                            <div class="amount-box balance">
                                <p>Balance Due</p>
                                <h4>GHS <?php echo number_format($bill->balance_due, 2); ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Patient Info -->
            <div class="row">
                <div class="col-md-12">
                    <div class="box box-info">
                        <div class="box-header">
                            <h3 class="box-title"><i class="fa fa-user"></i> Patient Information</h3>
                        </div>
                        <div class="box-body">
                            <div class="row">
                                <div class="col-md-3">
                                    <strong>Patient Name:</strong><br>
                                    <?php echo $bill->patient_name; ?>
                                </div>
                                <div class="col-md-3">
                                    <strong>Patient No:</strong><br>
                                    <?php echo $bill->patient_no; ?>
                                </div>
                                <div class="col-md-3">
                                    <strong>Payer Type:</strong><br>
                                    <?php echo $bill->payer_type; ?>
                                </div>
                                <div class="col-md-3">
                                    <strong>Visit ID:</strong><br>
                                    <?php echo $bill->visit_id; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bill Items -->
            <div class="row">
                <div class="col-md-8">
                    <div class="box">
                        <div class="box-header">
                            <h3 class="box-title"><i class="fa fa-list"></i> Bill Items</h3>
                        </div>
                        <div class="box-body">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Service</th>
                                        <th>Department</th>
                                        <th>Qty</th>
                                        <th>Unit Price</th>
                                        <th>Amount</th>
                                        <th>Gate</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($items as $item): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo $item->service_name; ?></strong><br>
                                            <small class="text-muted"><?php echo $item->service_type; ?></small>
                                        </td>
                                        <td><?php echo $item->department; ?></td>
                                        <td><?php echo number_format($item->quantity, 2); ?></td>
                                        <td>GHS <?php echo number_format($item->unit_price, 2); ?></td>
                                        <td>
                                            <?php if($item->discount_amount > 0): ?>
                                            <del class="text-muted"><?php echo number_format($item->gross_amount, 2); ?></del><br>
                                            <?php endif; ?>
                                            <strong>GHS <?php echo number_format($item->net_amount, 2); ?></strong>
                                        </td>
                                        <td>
                                            <span class="gate-status gate-<?php echo $item->gate_status; ?>">
                                                <?php echo $item->gate_status; ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="4" class="text-right">Total:</th>
                                        <th colspan="2">GHS <?php echo number_format($bill->total_amount, 2); ?></th>
                                    </tr>
                                    <?php if($bill->discount_amount > 0): ?>
                                    <tr>
                                        <th colspan="4" class="text-right">Discount:</th>
                                        <th colspan="2" class="text-success">-GHS <?php echo number_format($bill->discount_amount, 2); ?></th>
                                    </tr>
                                    <tr>
                                        <th colspan="4" class="text-right">Net Amount:</th>
                                        <th colspan="2">GHS <?php echo number_format($bill->net_amount, 2); ?></th>
                                    </tr>
                                    <?php endif; ?>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Payment History -->
                <div class="col-md-4">
                    <div class="box box-success">
                        <div class="box-header">
                            <h3 class="box-title"><i class="fa fa-money"></i> Payment History</h3>
                        </div>
                        <div class="box-body">
                            <?php if(!empty($payments)): ?>
                                <?php foreach($payments as $payment): ?>
                                <div class="item-row">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <strong><?php echo $payment->payment_method; ?></strong><br>
                                            <?php if (isset($payment->legacy_receipt_no) && trim((string)$payment->legacy_receipt_no) !== '') { ?>
                                                <small class="text-muted">OR: <?php echo htmlspecialchars((string)$payment->legacy_receipt_no); ?></small><br>
                                            <?php } ?>
                                            <small class="text-muted"><?php echo date('M d, H:i', strtotime($payment->collected_at)); ?></small>
                                        </div>
                                        <div class="col-md-6 text-right">
                                            <h4 class="text-success" style="margin: 0;">GHS <?php echo number_format($payment->amount, 2); ?></h4>
                                        </div>
                                    </div>
                                    <a href="<?php echo base_url(); ?>app/unified_billing/print_receipt/<?php echo $payment->payment_id; ?>" 
                                       class="btn btn-xs btn-default" target="_blank">
                                        <i class="fa fa-print"></i> Receipt
                                    </a>
									<?php if (isset($payment->legacy_receipt_no) && trim((string)$payment->legacy_receipt_no) !== '') { ?>
									<a href="<?php echo base_url(); ?>app/unified_billing/print_official_receipt/<?php echo $payment->payment_id; ?>"
									   class="btn btn-xs btn-success" target="_blank">
										<i class="fa fa-print"></i> Print OR
									</a>
									<a href="<?php echo base_url(); ?>app/unified_billing/print_official_receipt_pdf/<?php echo $payment->payment_id; ?>"
									   class="btn btn-xs btn-info" target="_blank">
										<i class="fa fa-file-pdf-o"></i> OR PDF
									</a>
									<?php } ?>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    <i class="fa fa-info-circle"></i> No payments recorded yet.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="box">
                        <div class="box-header">
                            <h3 class="box-title"><i class="fa fa-cog"></i> Actions</h3>
                        </div>
                        <div class="box-body">
                            <?php if($can_collect && $bill->payment_status != 'PAID'): ?>
                            <button class="btn btn-success btn-block" onclick="showPaymentModal(<?php echo $bill->bill_id; ?>, <?php echo $bill->balance_due; ?>)">
                                <i class="fa fa-money"></i> Record Payment
                            </button>
                            <?php endif; ?>
                            <a href="<?php echo base_url(); ?>app/unified_billing/print_bill/<?php echo $bill->bill_id; ?>" 
                               class="btn btn-primary btn-block" target="_blank">
                                <i class="fa fa-print"></i> Print Invoice
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payment Modal -->
            <div class="modal fade" id="paymentModal" tabindex="-1" role="dialog">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-green">
                            <h4 class="modal-title"><i class="fa fa-money"></i> Record Payment</h4>
                        </div>
                        <form id="paymentForm">
                            <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                            <div class="modal-body">
                                <input type="hidden" name="bill_id" id="payment_bill_id">
                                
                                <div class="form-group">
                                    <label>Balance Due</label>
                                    <input type="text" class="form-control" id="payment_balance" readonly style="font-size: 20px; font-weight: bold; color: red;">
                                </div>
                                
                                <div class="form-group">
                                    <label>Amount Paid <span class="text-danger">*</span></label>
                                    <input type="number" name="amount" id="payment_amount" class="form-control" step="0.01" min="0.01" required>
                                </div>
                                
                                <div class="form-group">
                                    <label>Payment Method <span class="text-danger">*</span></label>
                                    <select name="payment_method" class="form-control" required>
                                        <option value="CASH">Cash</option>
                                        <option value="MOMO">Mobile Money</option>
                                        <option value="BANK_TRANSFER">Bank Transfer</option>
                                        <option value="CARD">Card Payment</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label>Reference No</label>
                                    <input type="text" name="reference_no" class="form-control">
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-success"><i class="fa fa-save"></i> Record Payment</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        </section>
    </aside>
</div>

<script src="<?php echo base_url(); ?>public/js/jquery.min.js"></script>
<script src="<?php echo base_url(); ?>public/js/bootstrap.min.js"></script>
<script src="<?php echo base_url(); ?>public/js/AdminLTE/app.js"></script>
<script>
function showPaymentModal(billId, balance) {
    $('#payment_bill_id').val(billId);
    $('#payment_balance').val('GHS ' + balance.toFixed(2));
    $('#payment_amount').val(balance.toFixed(2));
    $('#paymentModal').modal('show');
}

$('#paymentForm').on('submit', function(e) {
    e.preventDefault();
    
    var formData = $(this).serialize();
    var submitBtn = $(this).find('button[type="submit"]');
    submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Processing...');
    
    $.ajax({
        url: '<?php echo base_url(); ?>app/unified_billing/record_payment',
        type: 'POST',
        data: formData,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                if (response.receipt_no) {
                    alert('Payment recorded successfully! OR: ' + response.receipt_no);
                } else {
                    alert('Payment recorded successfully!');
                }
                window.location.reload();
            } else {
                alert('Error: ' + (response.error || 'Failed to record payment'));
                submitBtn.prop('disabled', false).html('<i class="fa fa-save"></i> Record Payment');
            }
        },
        error: function() {
            alert('Network error. Please try again.');
            submitBtn.prop('disabled', false).html('<i class="fa fa-save"></i> Record Payment');
        }
    });
});
</script>

</body>
</html>
