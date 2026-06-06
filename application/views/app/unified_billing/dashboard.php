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
        .billing-card { border-left: 4px solid #00c0ef; padding: 15px; margin-bottom: 20px; }
        .billing-card.paid { border-left-color: #00a65a; }
        .billing-card.pending { border-left-color: #f39c12; }
        .billing-card.partial { border-left-color: #f56954; }
        .stat-box { text-align: center; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .stat-box h3 { margin: 0; font-size: 28px; font-weight: bold; }
        .stat-box p { margin: 5px 0 0 0; font-size: 14px; }
        .stat-box.primary { background: #3c8dbc; color: white; }
        .stat-box.success { background: #00a65a; color: white; }
        .stat-box.warning { background: #f39c12; color: white; }
        .stat-box.danger { background: #f56954; color: white; }
        .bill-row { cursor: pointer; }
        .bill-row:hover { background: #f5f5f5; }
        .status-badge { padding: 4px 12px; border-radius: 12px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .status-paid { background: #00a65a; color: white; }
        .status-pending { background: #f39c12; color: white; }
        .status-partial { background: #f56954; color: white; }
        .status-cancelled { background: #999; color: white; }
        .status-refunded { background: #777; color: white; }
        .quick-action { margin-right: 10px; }
    </style>
</head>
<body class="skin-blue">

<?php require_once(APPPATH.'views/include/header.php');?>
<?php require_once(APPPATH.'views/include/sidebar.php');?>

<div class="wrapper row-offcanvas row-offcanvas-left">
    <aside class="right-side">
        <section class="content-header">
            <h1>Unified Billing Dashboard <small>Single Source of Truth</small></h1>
            <ol class="breadcrumb">
                <li><a href="<?php echo base_url(); ?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                <li class="active">Unified Billing</li>
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

            <!-- Quick Actions -->
            <div class="row">
                <div class="col-md-12">
                    <div class="box box-primary">
                        <div class="box-header">
                            <h3 class="box-title"><i class="fa fa-bolt"></i> Quick Actions</h3>
                        </div>
                        <div class="box-body">
                            <a href="<?php echo base_url(); ?>app/unified_billing/search" class="btn btn-app quick-action">
                                <i class="fa fa-search"></i> Search Bills
                            </a>
                            <?php if($can_collect_payment): ?>
                            <button class="btn btn-app quick-action" onclick="showQuickPaymentModal()">
                                <i class="fa fa-money"></i> Quick Payment
                            </button>
                            <?php endif; ?>
                            <?php if($is_admin): ?>
                            <a href="<?php echo base_url(); ?>app/unified_billing/today" class="btn btn-app quick-action">
                                <i class="fa fa-list"></i> Today's Bills
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Summary Stats -->
            <div class="row">
                <div class="col-md-3">
                    <div class="stat-box primary">
                        <h3><?php echo number_format($summary['total_bills']); ?></h3>
                        <p><i class="fa fa-file-text-o"></i> Total Bills Today</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-box success">
                        <h3>GHS <?php echo number_format($summary['total_paid'], 2); ?></h3>
                        <p><i class="fa fa-check-circle"></i> Collected Today</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-box warning">
                        <h3>GHS <?php echo number_format($summary['total_pending'], 2); ?></h3>
                        <p><i class="fa fa-clock-o"></i> Outstanding Balance</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-box danger">
                        <h3><?php echo isset($summary['pending_count']) ? $summary['pending_count'] : count($pending_bills); ?></h3>
                        <p><i class="fa fa-exclamation-triangle"></i> Pending Bills</p>
                    </div>
                </div>
            </div>

            <!-- Today's Status Breakdown -->
            <div class="row">
                <div class="col-md-12">
                    <div class="box">
                        <div class="box-header">
                            <h3 class="box-title"><i class="fa fa-pie-chart"></i> Today's Status Breakdown</h3>
                        </div>
                        <div class="box-body">
                            <div class="row">
                                <div class="col-md-4 text-center">
                                    <div class="small-box bg-green">
                                        <div class="inner">
                                            <h4>GHS <?php echo number_format($summary['total_paid'], 2); ?></h4>
                                            <p>Cash Collected</p>
                                        </div>
                                        <div class="icon"><i class="fa fa-money"></i></div>
                                        <span class="small-box-footer">Revenue <i class="fa fa-arrow-circle-right"></i></span>
                                    </div>
                                </div>
                                <div class="col-md-4 text-center">
                                    <div class="small-box bg-yellow">
                                        <div class="inner">
                                            <h4>GHS <?php echo number_format($summary['total_pending'], 2); ?></h4>
                                            <p>Outstanding</p>
                                        </div>
                                        <div class="icon"><i class="fa fa-clock-o"></i></div>
                                        <span class="small-box-footer">Pending <i class="fa fa-arrow-circle-right"></i></span>
                                    </div>
                                </div>
                                <div class="col-md-4 text-center">
                                    <div class="small-box bg-aqua">
                                        <div class="inner">
                                            <h4>GHS <?php echo number_format($summary['total_amount'], 2); ?></h4>
                                            <p>Total Billed Today</p>
                                        </div>
                                        <div class="icon"><i class="fa fa-file-text-o"></i></div>
                                        <span class="small-box-footer">Bills <i class="fa fa-arrow-circle-right"></i></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pending Bills Table -->
            <div class="row">
                <div class="col-md-12">
                    <div class="box box-info">
                        <div class="box-header">
                            <h3 class="box-title"><i class="fa fa-exclamation-circle"></i> Pending Bills Requiring Payment</h3>
                            <div class="box-tools pull-right">
                                <a href="<?php echo base_url(); ?>app/unified_billing/pending" class="btn btn-sm btn-primary">View All Pending</a>
                            </div>
                        </div>
                        <div class="box-body table-responsive">
                            <?php if(!empty($pending_bills)): ?>
                            <table class="table table-hover table-bordered">
                                <thead>
                                    <tr>
                                        <th>Ref No</th>
                                        <th>Patient</th>
                                        <th>Item</th>
                                        <th>Type</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($pending_bills as $bill): 
                                        $balance = isset($bill->balance_due) ? $bill->balance_due : ($bill->total_amount - $bill->amount_paid);
                                        $status = ($bill->amount_paid > 0) ? 'PARTIAL' : 'PENDING';
                                        $bill_type = isset($bill->bill_type) ? $bill->bill_type : 'INVOICE';
                                    ?>
                                    <tr class="bill-row">
                                        <td>
                                            <strong><?php echo $bill->invoice_no; ?></strong>
                                            <?php if($bill_type == 'LAB'): ?>
                                            <span class="label label-info">Lab</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo $bill->patient_name ?: 'N/A'; ?><br>
                                            <small class="text-muted"><?php echo $bill->patient_no; ?></small>
                                        </td>
                                        <td><?php echo isset($bill->item_name) ? $bill->item_name : 'Invoice'; ?></td>
                                        <td><?php echo isset($bill->visit_type) ? $bill->visit_type : 'OPD'; ?></td>
                                        <td><strong class="text-danger">GHS <?php echo number_format($balance, 2); ?></strong></td>
                                        <td>
                                            <span class="status-badge status-<?php echo strtolower($status); ?>">
                                                <?php echo $status; ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('d M H:i', strtotime($bill->created_at)); ?></td>
                                        <td onclick="event.stopPropagation()">
                                            <?php if($can_collect): ?>
                                                <?php if($bill_type == 'LAB'): ?>
                                                <a href="<?php echo base_url(); ?>app/pos/patient/<?php echo $bill->patient_no; ?>" class="btn btn-xs btn-success">
                                                    <i class="fa fa-money"></i> Bill
                                                </a>
                                                <?php else: ?>
                                                <a href="<?php echo base_url(); ?>app/pos/receipt/<?php echo $bill->invoice_no; ?>" class="btn btn-xs btn-success">
                                                    <i class="fa fa-money"></i> Pay
                                                </a>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fa fa-info-circle"></i> No pending bills found for today. Great job!
                            </div>
                            <?php endif; ?>
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
                                        <option value="CHEQUE">Cheque</option>
                                        <option value="NHIS">NHIS</option>
                                        <option value="INSURANCE">Insurance</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label>Reference No (for MOMO/Transfer/Cheque)</label>
                                    <input type="text" name="reference_no" class="form-control">
                                </div>
                                
                                <div class="form-group">
                                    <label>Notes</label>
                                    <textarea name="notes" class="form-control" rows="2"></textarea>
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

function showQuickPaymentModal() {
    // For quick payment without pre-selected bill
    $('#payment_bill_id').val('');
    $('#payment_balance').val('Enter Bill No first');
    $('#payment_amount').val('');
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
                alert('Payment recorded successfully!\nReceipt: ' + response.payment_no);
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

// Auto-refresh every 60 seconds for live updates
setInterval(function() {
    // Only refresh if no modal is open
    if (!$('.modal:visible').length) {
        // Check for new pending bills via AJAX
        $.get('<?php echo base_url(); ?>app/unified_billing/pending_count', function(data) {
            // Could update badges here
        });
    }
}, 60000);
</script>

</body>
</html>
