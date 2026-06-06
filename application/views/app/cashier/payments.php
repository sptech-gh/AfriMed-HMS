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
    <title><?php echo isset($companyInfo->site_title) ? $companyInfo->site_title : 'HMS'; ?> — Payment Collection</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
</head>
<body class="skin-blue">
    <?php require_once(APPPATH.'views/include/header.php');?>
    <div class="wrapper row-offcanvas row-offcanvas-left">
        <?php require_once(APPPATH.'views/include/sidebar.php');?>
        <aside class="right-side">
            <section class="content-header">
                <h1>Payment Collection <small>Collect payments for invoices</small></h1>
            </section>
            <section class="content">
                <?php echo isset($message) ? $message : ''; ?>

                <!-- Summary Boxes -->
                <div class="row">
                    <div class="col-lg-3 col-xs-6">
                        <div class="small-box bg-aqua">
                            <div class="inner">
                                <h3><?php echo isset($summary['today_invoices']) ? $summary['today_invoices'] : 0; ?></h3>
                                <p>Today's Invoices</p>
                            </div>
                            <div class="icon"><i class="ion ion-document-text"></i></div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-xs-6">
                        <div class="small-box bg-green">
                            <div class="inner">
                                <h3><?php echo number_format(isset($summary['today_collections']) ? $summary['today_collections'] : 0, 2); ?></h3>
                                <p>Today's Collections</p>
                            </div>
                            <div class="icon"><i class="ion ion-cash"></i></div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-xs-6">
                        <div class="small-box bg-red">
                            <div class="inner">
                                <h3><?php echo isset($summary['unpaid_count']) ? $summary['unpaid_count'] : 0; ?></h3>
                                <p>Unpaid Invoices</p>
                            </div>
                            <div class="icon"><i class="ion ion-alert-circled"></i></div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-xs-6">
                        <div class="small-box bg-yellow">
                            <div class="inner">
                                <h3><?php echo number_format(isset($summary['unpaid_total']) ? $summary['unpaid_total'] : 0, 2); ?></h3>
                                <p>Outstanding Balance</p>
                            </div>
                            <div class="icon"><i class="ion ion-pie-graph"></i></div>
                        </div>
                    </div>
                </div>

				<?php if (isset($queue_summary) && is_array($queue_summary) && (int)($queue_summary['pending_count'] ?? 0) > 0) { ?>
				<div class="callout callout-warning" style="margin-top:10px;">
					<h4><i class="fa fa-clock-o"></i> Pending billable items</h4>
					<p>
						There are <strong><?php echo (int)($queue_summary['pending_count'] ?? 0); ?></strong> items awaiting invoicing
						(Amount: <strong>GHS <?php echo number_format((float)($queue_summary['pending_amount'] ?? 0), 2); ?></strong>).
						<a class="btn btn-sm btn-warning" style="margin-left:8px;" href="<?php echo base_url();?>app/cashier/billing_queue">
							<i class="fa fa-list"></i> Open Billing Queue
						</a>
					</p>
				</div>
				<?php } ?>

                <!-- Search and Filter -->
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-search"></i> Search Invoices</h3>
                    </div>
                    <div class="box-body">
                        <form method="get" action="<?php echo base_url();?>app/cashier/payments" class="form-inline">
                            <div class="form-group" style="margin-right:15px">
                                <input type="text" name="search" class="form-control" placeholder="Invoice #, Patient ID, Name, Phone..." value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" style="width:280px">
                            </div>
                            <div class="form-group" style="margin-right:15px">
                                <select name="status" class="form-control">
                                    <option value="unpaid" <?php echo $status === 'unpaid' ? 'selected' : ''; ?>>Unpaid Only</option>
                                    <option value="paid" <?php echo $status === 'paid' ? 'selected' : ''; ?>>Paid Only</option>
                                    <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All Invoices</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> Search</button>
                            <a href="<?php echo base_url();?>app/cashier/payments" class="btn btn-default"><i class="fa fa-refresh"></i> Reset</a>
                        </form>
                    </div>
                </div>

                <!-- Invoices Table -->
                <div class="box box-info">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-list"></i> Invoices</h3>
                        <div class="box-tools pull-right">
                            <?php echo isset($pagination) ? $pagination : ''; ?>
                        </div>
                    </div>
                    <div class="box-body table-responsive no-padding">
                        <table class="table table-hover table-striped">
                            <thead>
                                <tr>
                                    <th>Invoice #</th>
                                    <th>Patient ID</th>
                                    <th>Patient Name</th>
                                    <th>Phone</th>
                                    <th>Date</th>
                                    <th class="text-right">Total</th>
                                    <th class="text-right">Paid</th>
                                    <th class="text-right">Balance</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (isset($invoices) && count($invoices) > 0) { ?>
                                    <?php foreach ($invoices as $inv) { ?>
                                    <tr>
                                        <td><a href="<?php echo base_url();?>app/cashier/invoice/<?php echo $inv->invoice_no; ?>"><?php echo $inv->invoice_no; ?></a></td>
                                        <td><?php echo $inv->patient_no; ?></td>
                                        <td><?php echo $inv->patient_name; ?></td>
                                        <td><?php echo isset($inv->phone) ? $inv->phone : '-'; ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($inv->dDate)); ?></td>
                                        <td class="text-right"><?php echo number_format($inv->total_amount, 2); ?></td>
                                        <td class="text-right"><?php echo number_format($inv->amount_paid, 2); ?></td>
                                        <td class="text-right">
                                            <?php if ((float)$inv->balance > 0) { ?>
                                                <span class="text-danger"><strong><?php echo number_format($inv->balance, 2); ?></strong></span>
                                            <?php } else { ?>
                                                <span class="text-success">0.00</span>
                                            <?php } ?>
                                        </td>
                                        <td>
                                            <?php if ((float)$inv->balance > 0) { ?>
                                                <button type="button" class="btn btn-xs btn-success" data-toggle="modal" data-target="#paymentModal" 
                                                    data-invoice="<?php echo $inv->invoice_no; ?>" 
                                                    data-patient="<?php echo htmlspecialchars($inv->patient_name, ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-balance="<?php echo $inv->balance; ?>">
                                                    <i class="fa fa-money"></i> Pay
                                                </button>
                                            <?php } else { ?>
                                                <span class="label label-success">Paid</span>
                                            <?php } ?>
                                            <a href="<?php echo base_url();?>app/cashier/invoice/<?php echo $inv->invoice_no; ?>" class="btn btn-xs btn-info"><i class="fa fa-eye"></i></a>
                                        </td>
                                    </tr>
                                    <?php } ?>
                                <?php } else { ?>
                                    <tr><td colspan="9" class="text-center text-muted">No invoices found</td></tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="box-footer clearfix">
                        <?php echo isset($pagination) ? $pagination : ''; ?>
                    </div>
                </div>

            </section>
        </aside>
    </div>

    <!-- Payment Modal -->
    <div class="modal fade" id="paymentModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form method="post" action="<?php echo base_url();?>app/cashier/process_payment">
                    <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                    <div class="modal-header bg-green">
                        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                        <h4 class="modal-title"><i class="fa fa-money"></i> Collect Payment</h4>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="invoice_no" id="pay_invoice_no">
                        <div class="form-group">
                            <label>Invoice #</label>
                            <p class="form-control-static" id="pay_invoice_display"></p>
                        </div>
                        <div class="form-group">
                            <label>Patient</label>
                            <p class="form-control-static" id="pay_patient_display"></p>
                        </div>
                        <div class="form-group">
                            <label>Balance Due</label>
                            <p class="form-control-static text-danger" id="pay_balance_display"></p>
                        </div>
                        <div class="form-group">
                            <label>Amount to Pay <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-addon">GHS</span>
                                <input type="number" name="amount" id="pay_amount" class="form-control" step="0.01" min="0.01" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Payment Method <span class="text-danger">*</span></label>
                            <select name="payment_method" id="pay_method" class="form-control" required>
                                <?php if (isset($payment_methods)) { foreach ($payment_methods as $pm) { ?>
                                    <option value="<?php echo $pm->method_code; ?>" data-ref="<?php echo $pm->requires_reference; ?>"><?php echo $pm->method_name; ?></option>
                                <?php } } ?>
                            </select>
                        </div>
                        <div class="form-group" id="reference_group" style="display:none">
                            <label>Reference Number</label>
                            <input type="text" name="reference" class="form-control" placeholder="Transaction ID / Reference">
                        </div>
                        <div class="form-group">
                            <label>Notes</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="Optional notes..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success"><i class="fa fa-check"></i> Record Payment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="<?php echo base_url();?>public/js/jquery.min.js"></script>
    <script src="<?php echo base_url();?>public/js/bootstrap.min.js"></script>
    <script src="<?php echo base_url();?>public/js/AdminLTE/app.js"></script>
    <script>
    $(document).ready(function() {
        $('#paymentModal').on('show.bs.modal', function(e) {
            var btn = $(e.relatedTarget);
            var invoice = btn.data('invoice');
            var patient = btn.data('patient');
            var balance = parseFloat(btn.data('balance'));
            
            $('#pay_invoice_no').val(invoice);
            $('#pay_invoice_display').text(invoice);
            $('#pay_patient_display').text(patient);
            $('#pay_balance_display').text('GHS ' + balance.toFixed(2));
            $('#pay_amount').val(balance.toFixed(2)).attr('max', balance);
        });

        $('#pay_method').on('change', function() {
            var reqRef = $(this).find(':selected').data('ref');
            if (reqRef == 1) {
                $('#reference_group').show();
            } else {
                $('#reference_group').hide();
            }
        });
    });
    </script>
</body>
</html>
