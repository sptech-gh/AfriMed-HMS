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
    <link href="<?php echo base_url(); ?>public/css/custom.css" rel="stylesheet" type="text/css">
    <style>
        .bill-card { border-left: 4px solid #f39c12; padding: 15px; margin-bottom: 15px; background: #fff; border-radius: 4px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .bill-card.paid { border-left-color: #00a65a; }
        .bill-card.partial { border-left-color: #f56954; }
        .status-badge { padding: 4px 12px; border-radius: 12px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .status-pending { background: #f39c12; color: white; }
        .status-partial { background: #f56954; color: white; }
        .amount-due { font-size: 24px; font-weight: bold; color: #f56954; }
        .amount-paid { font-size: 18px; color: #00a65a; }
        .patient-info { margin-bottom: 10px; }
        .patient-name { font-size: 18px; font-weight: bold; color: #333; }
        .bill-meta { color: #666; font-size: 12px; }
        .quick-pay-btn { margin-top: 10px; }
    </style>
</head>
<body class="skin-blue">

<?php require_once(APPPATH.'views/include/header.php');?>
<?php require_once(APPPATH.'views/include/sidebar.php');?>

<div class="wrapper row-offcanvas row-offcanvas-left">
    <aside class="right-side">
        <section class="content-header">
            <h1><?php echo $page_title; ?> <small>Quick Payment Collection</small></h1>
            <ol class="breadcrumb">
                <li><a href="<?php echo base_url(); ?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                <li><a href="<?php echo base_url(); ?>app/ebilling">Unified Billing</a></li>
                <li class="active">Collect Payment</li>
            </ol>
        </section>

        <section class="content">
            <!-- Notification Messages -->
            <?php if($this->session->flashdata('message')): ?>
                <div class="alert alert-success alert-dismissable">
                    <i class="fa fa-check"></i>
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                    <?php echo $this->session->flashdata('message');?>
                </div>
            <?php endif; ?>

            <?php if($this->session->flashdata('error')): ?>
                <div class="alert alert-danger alert-dismissable">
                    <i class="fa fa-ban"></i>
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                    <?php echo $this->session->flashdata('error');?>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-12">
                    <div class="box box-primary">
                        <div class="box-header">
                            <h3 class="box-title"><i class="fa fa-money"></i> Pending Bills for Payment</h3>
                            <div class="box-tools pull-right">
                                <a href="<?php echo base_url(); ?>app/ebilling" class="btn btn-default btn-sm">
                                    <i class="fa fa-arrow-left"></i> Back to Dashboard
                                </a>
                            </div>
                        </div>
                        <div class="box-body">
                            <?php if (empty($bills)): ?>
                                <div class="alert alert-info">
                                    <i class="fa fa-info-circle"></i> No pending bills found for today. All bills have been paid!
                                </div>
                            <?php else: ?>
                                <div class="row">
                                    <?php foreach ($bills as $bill): ?>
                                        <div class="col-md-6">
                                            <div class="bill-card <?php echo strtolower($bill->payment_status); ?>">
                                                <div class="patient-info">
                                                    <div class="patient-name">
                                                        <i class="fa fa-user"></i> 
                                                        <?php echo $bill->patient_name ?? 'Unknown'; ?>
                                                    </div>
                                                    <div class="bill-meta">
                                                        Bill #<?php echo $bill->bill_no; ?> | 
                                                        <?php echo $bill->visit_type; ?> | 
                                                        <?php echo date('M d, Y H:i', strtotime($bill->created_at)); ?>
                                                    </div>
                                                </div>
                                                <div class="row">
                                                    <div class="col-xs-6">
                                                        <div class="amount-due">
                                                            GH₵<?php echo number_format($bill->balance_due, 2); ?>
                                                        </div>
                                                        <small>Balance Due</small>
                                                    </div>
                                                    <div class="col-xs-6">
                                                        <div class="amount-paid">
                                                            GH₵<?php echo number_format($bill->total_paid, 2); ?>
                                                        </div>
                                                        <small>Total Paid</small>
                                                    </div>
                                                </div>
                                                <div class="row" style="margin-top: 10px;">
                                                    <div class="col-xs-6">
                                                        <span class="status-badge status-<?php echo strtolower($bill->payment_status); ?>">
                                                            <?php echo $bill->payment_status; ?>
                                                        </span>
                                                    </div>
                                                    <div class="col-xs-6 text-right">
                                                        <a href="<?php echo base_url(); ?>app/ebilling/view_bill/<?php echo $bill->bill_id; ?>" class="btn btn-primary btn-sm">
                                                            <i class="fa fa-eye"></i> View Bill
                                                        </a>
                                                        <?php if ($bill->payment_status !== 'PAID' && $can_collect): ?>
                                                            <button class="btn btn-success btn-sm" onclick="showPaymentModal(<?php echo $bill->bill_id; ?>, <?php echo $bill->balance_due; ?>)">
                                                                <i class="fa fa-money"></i> Pay
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </aside>
</div>

<!-- Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title"><i class="fa fa-money"></i> Record Payment</h4>
            </div>
            <div class="modal-body">
                <form id="paymentForm">
                    <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                    <input type="hidden" name="bill_id" id="payment_bill_id">
                    <div class="form-group">
                        <label>Amount Due</label>
                        <input type="text" class="form-control" id="display_amount_due" readonly>
                    </div>
                    <div class="form-group">
                        <label>Payment Amount <span class="text-danger">*</span></label>
                        <input type="number" name="amount" id="payment_amount" class="form-control" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label>Payment Method <span class="text-danger">*</span></label>
                        <select name="payment_method" class="form-control" required>
                            <option value="Cash">Cash</option>
                            <option value="Card">Card</option>
                            <option value="Mobile Money">Mobile Money</option>
                            <option value="NHIS">NHIS</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="Cheque">Cheque</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Reference Number</label>
                        <input type="text" name="reference_no" class="form-control" placeholder="Transaction ID, Cheque number, etc.">
                    </div>
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" onclick="submitPayment()">
                    <i class="fa fa-check"></i> Record Payment
                </button>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo base_url(); ?>public/js/jquery.min.js"></script>
<script src="<?php echo base_url(); ?>public/js/bootstrap.min.js"></script>
<script src="<?php echo base_url(); ?>public/js/AdminLTE/app.js"></script>

<script>
function showPaymentModal(billId, amountDue) {
    $('#payment_bill_id').val(billId);
    $('#display_amount_due').val('GH₵' + amountDue.toFixed(2));
    $('#payment_amount').val(amountDue.toFixed(2));
    $('#paymentModal').modal('show');
}

function submitPayment() {
    var formData = $('#paymentForm').serialize();
    $.ajax({
        url: '<?php echo base_url(); ?>app/ebilling/record_payment',
        type: 'POST',
        data: formData,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                alert('Payment recorded successfully!');
                location.reload();
            } else {
                alert('Error: ' + (response.error || 'Failed to record payment'));
            }
        },
        error: function() {
            alert('Network error. Please try again.');
        }
    });
}
</script>

</body>
</html>
