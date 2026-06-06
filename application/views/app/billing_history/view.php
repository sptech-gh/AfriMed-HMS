<!DOCTYPE html>
<html>
    <head>
<head>

        <meta charset="UTF-8">
        <title>Hebrew Medical Center</title>
        <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">

  

        <link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
        <link href="<?php echo base_url();?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
        <link href="<?php echo base_url();?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
        <link href="<?php echo base_url();?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
        
           <!----------BOOTSTRAP DATEPICKER----------------------------->
    	<link rel="stylesheet" href="<?php echo base_url();?>public/datepicker/css/datepicker.css">
		<!---------------------------------------------------------->
        
        
        <!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->
        <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
        <!--[if lt IE 9]>
          <script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>
          <script src="https://oss.maxcdn.com/libs/respond.js/1.3.0/respond.min.js"></script>
        <![endif]-->
        
    </head>  
    <body class="skin-blue">
        <!-- header logo: style can be found in header.less -->
        <?php require_once(APPPATH.'views/include/header.php');?>
        
        <div class="wrapper row-offcanvas row-offcanvas-left">
            
            <?php require_once(APPPATH.'views/include/sidebar.php');?>

            <!-- Right side column. Contains the navbar and content of the page -->
            <aside class="right-side">                
                <!-- Content Header (Page header) -->
                <section class="content-header">
                    <h1>Billing Details</h1>
                    <ol class="breadcrumb">
                        <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                        <li><a href="#">Billing</a></li>
                        <li><a href="<?php echo base_url()?>app/billing_history"> Billing History</a></li>
                        <li class="active">Billing Details</li>
                    </ol>
                </section>

                <!-- Main content -->
                <section class="content">

                 <?php echo isset($message) ? $message : ''; ?>

                 <?php
                 $pStatus = isset($payment_status) ? $payment_status : 'UNPAID';
                 $paidAmt = isset($paid_amount) ? (float)$paid_amount : 0;
                 $balDue = isset($balance_due) ? (float)$balance_due : 0;
                 $totalAmt = ($header && isset($header->total_amount)) ? (float)$header->total_amount : 0;
                 if ($pStatus === 'PAID') {
                     $statusBadge = '<span class="label label-success" style="font-size:14px;"><i class="fa fa-check-circle"></i> PAID</span>';
                 } else if ($pStatus === 'PARTIAL') {
                     $statusBadge = '<span class="label label-warning" style="font-size:14px;"><i class="fa fa-adjust"></i> PARTIAL</span>';
                 } else {
                     $statusBadge = '<span class="label label-danger" style="font-size:14px;"><i class="fa fa-exclamation-circle"></i> UNPAID</span>';
                 }
                 ?>

                 <div class="row">
                    <div class="col-md-3">
                        <div class="info-box">
                            <span class="info-box-icon bg-aqua"><i class="fa fa-file-text-o"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Total Amount</span>
                                <span class="info-box-number"><?php echo number_format($totalAmt, 2); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="info-box">
                            <span class="info-box-icon bg-green"><i class="fa fa-money"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Paid Amount</span>
                                <span class="info-box-number"><?php echo number_format($paidAmt, 2); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="info-box">
                            <span class="info-box-icon bg-red"><i class="fa fa-clock-o"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Balance Due</span>
                                <span class="info-box-number"><?php echo number_format($balDue, 2); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="info-box">
                            <span class="info-box-icon <?php echo $pStatus === 'PAID' ? 'bg-green' : ($pStatus === 'PARTIAL' ? 'bg-yellow' : 'bg-red'); ?>"><i class="fa fa-credit-card"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Status</span>
                                <span class="info-box-number"><?php echo $statusBadge; ?></span>
                            </div>
                        </div>
                    </div>
                 </div>
                 
                 <div class="row">
                 	<div class="col-md-12">
                    
                    	<div class="nav-tabs-custom">
                        	<ul class="nav nav-tabs">
                               	<li class="active"><a href="#tab_1" data-toggle="tab"><strong>Billing List</strong></a></li>
                            	<li><a href="#tab_2" data-toggle="tab">Header Details</a></li>
                                <li><a href="#tab_3" data-toggle="tab">Patient Information</a></li>
                                <li><a href="#tab_4" data-toggle="tab">Payment History</a></li>
                            </ul>
                            <div class="tab-content">
                            	<div class="tab-pane active" id="tab_1">
                                	<a class="btn btn-primary" href="<?php echo base_url()?>app/opd/printOR/<?php echo $patientInfo->IO_ID?>/<?php echo $patientInfo->patient_no?>/<?php echo $header->invoice_no?>" target="_blank"><i class="fa fa-print"></i> Print Receipt</a>
                                    <a class="btn btn-default" href="<?php echo base_url()?>app/opd/printInv/<?php echo $patientInfo->IO_ID?>/<?php echo $patientInfo->patient_no?>/<?php echo $header->invoice_no?>" target="_blank"><i class="fa fa-print"></i> Print Invoice</a>
                                    <?php if ($pStatus !== 'PAID') { ?>
                                    <a class="btn btn-success" data-toggle="modal" data-target="#acceptPaymentModal"><i class="fa fa-credit-card"></i> Accept Payment</a>
                                    <?php } ?>
                                	<table class="table table-hover" style="margin-top:10px;">
                                    <thead>
                                    	<tr>
                                        	<th>Particular Name</th>
                                            <th>Qty</th>
                                            <th>Rate</th>
                                            <th>Amount</th>
                                            <th>Remarks</th>
                                            <th>Source</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    	<?php foreach($details as $detailsRow){?>
                                        <tr>
                                        	<td><?php echo $detailsRow->bill_name?></td>
                                            <td><?php echo $detailsRow->qty?></td>
                                            <td><?php echo number_format($detailsRow->rate,2)?></td>
                                            <td><?php echo number_format($detailsRow->amount,2)?></td>
                                            <td><?php echo $detailsRow->note?></td>
                                            <td>
												<?php
													$src = '';
													if (isset($detailsRow->source_module) && trim((string)$detailsRow->source_module) !== '') {
														$src .= (string)$detailsRow->source_module;
													}
													if (isset($detailsRow->source_ref) && trim((string)$detailsRow->source_ref) !== '') {
														$src .= ($src !== '' ? ' ' : '').(string)$detailsRow->source_ref;
													}
													echo ($src !== '') ? htmlspecialchars($src, ENT_QUOTES, 'UTF-8') : '-';
												?>
											</td>
                                        </tr>
                                        <?php }?>
                                    </tbody>
                                    </table>
                                </div>
                                <div class="tab-pane" id="tab_2">
                                	<table class="table">
                                    <tbody>
                                    	<tr>
                                        	<td width="30%"><strong>Invoice No.</strong></td>
                                            <td><?php echo $header->invoice_no?></td>
                                        </tr>
                                        <tr>
                                        	<td><strong>Invoice Date</strong></td>
                                            <td><?php echo $header->dDate?></td>
                                        </tr>
                                        <tr>
                                        	<td><strong>Payment Type</strong></td>
                                            <td><?php echo $header->payment_type?></td>
                                        </tr>
                                        <tr>
                                        	<td><strong>Total Items</strong></td>
                                            <td><?php echo $header->total_purchased?></td>
                                        </tr>
                                        <tr>
                                        	<td><strong>Total Amount</strong></td>
											<td><strong><?php echo number_format($header->total_amount,2);?></strong></td>
										</tr>
										<tr>
											<td><strong>Payment Status</strong></td>
											<td><?php echo $statusBadge; ?></td>
										</tr>
										<tr>
											<td><strong>Paid Amount</strong></td>
											<td><?php echo number_format($paidAmt,2);?></td>
										</tr>
										<tr>
											<td><strong>Balance Due</strong></td>
											<td style="<?php echo $balDue > 0 ? 'color:#dd4b39;font-weight:bold;' : 'color:#00a65a;'; ?>"><?php echo number_format($balDue,2);?></td>
										</tr>
                                        <tr>
                                        	<td><strong>Remarks</strong></td>
                                            <td><?php echo $header->remarks?></td>
                                        </tr>
                                    </tbody>
                                    </table>
                                </div>
                                <div class="tab-pane" id="tab_3">
                                	<table class="table">
                                    <tbody>
                                    	<tr>
                                        	<td width="30%"><strong>Patient No.</strong></td>
                                            <td><?php echo $patientInfo->patient_no?></td>
                                        </tr>
                                        <tr>
                                        	<td><strong>Patient IOP No.</strong></td>
                                            <td><?php echo $patientInfo->IO_ID?></td>
                                        </tr>
                                        <tr>
                                        	<td><strong>Patient Name</strong></td>
                                            <td><?php echo $patientInfo->patient?></td>
                                        </tr>
                                        <tr>
                                        	<td><strong>Date Visit</strong></td>
                                            <td><?php echo $patientInfo->date_visit?></td>
                                        </tr>
                                        <tr>
                                        	<td><strong>Time Visit</strong></td>
                                            <td><?php echo $patientInfo->time_visit?></td>
                                        </tr>
                                    </tbody>
                                    </table>
                                </div>
                                <div class="tab-pane" id="tab_4">
                                    <?php $payments = isset($payment_history) ? $payment_history : array(); ?>
                                    <?php if (count($payments) > 0) { ?>
                                    <table class="table table-hover table-striped">
                                    <thead>
                                        <tr>
                                            <th>Receipt No.</th>
                                            <th>Date</th>
                                            <th>Amount Paid</th>
                                            <th>Payment Type</th>
                                            <th>Change</th>
                                            <?php if (isset($payments[0]) && isset($payments[0]->cashier_id)) { ?><th>Cashier</th><?php } ?>
                                            <?php if (isset($payments[0]) && isset($payments[0]->notes)) { ?><th>Notes</th><?php } ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $totalPaid = 0; foreach ($payments as $pay) { $totalPaid += (float)$pay->amountPaid; ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($pay->receipt_no, ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo date('M d, Y H:i', strtotime($pay->dDate)); ?></td>
                                            <td><strong><?php echo number_format((float)$pay->amountPaid, 2); ?></strong></td>
                                            <td><?php echo htmlspecialchars(isset($pay->payment_method) ? $pay->payment_method : $pay->payment_type, ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo number_format((float)$pay->change, 2); ?></td>
                                            <?php if (isset($pay->cashier_id)) { ?><td><?php echo htmlspecialchars((string)$pay->cashier_id, ENT_QUOTES, 'UTF-8'); ?></td><?php } ?>
                                            <?php if (isset($pay->notes)) { ?><td><?php echo htmlspecialchars((string)$pay->notes, ENT_QUOTES, 'UTF-8'); ?></td><?php } ?>
                                        </tr>
                                        <?php } ?>
                                    </tbody>
                                    <tfoot>
                                        <tr style="font-weight:bold; background-color:#f5f5f5;">
                                            <td colspan="2">Total Payments</td>
                                            <td><?php echo number_format($totalPaid, 2); ?></td>
                                            <td colspan="4"></td>
                                        </tr>
                                    </tfoot>
                                    </table>
                                    <?php } else { ?>
                                    <div class="alert alert-info"><i class="fa fa-info-circle"></i> No payments recorded for this invoice.</div>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                        
                    </div>
                 </div>
                 
                 
                </section><!-- /.content -->

                <?php if ($pStatus !== 'PAID') { ?>
                <div class="modal fade" id="acceptPaymentModal" tabindex="-1" role="dialog">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form method="post" action="<?php echo base_url(); ?>app/pos/accept_payment" onsubmit="return validatePaymentForm();">
                            <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                            <input type="hidden" name="invoice_no" value="<?php echo $header->invoice_no; ?>">
                            <div class="modal-header">
                                <button type="button" class="close" data-dismiss="modal">&times;</button>
                                <h4 class="modal-title"><i class="fa fa-credit-card"></i> Accept Payment — Invoice <?php echo $header->invoice_no; ?></h4>
                            </div>
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Total Amount</label>
                                            <input type="text" class="form-control" value="<?php echo number_format($totalAmt, 2); ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Balance Due</label>
                                            <input type="text" class="form-control" id="pm_balance" value="<?php echo number_format($balDue, 2); ?>" readonly style="color:#dd4b39;font-weight:bold;">
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Amount to Pay <span style="color:red;">*</span></label>
                                    <input type="number" step="0.01" min="0.01" max="<?php echo round($balDue + 0.01, 2); ?>" class="form-control" name="amount_paid" id="pm_amount" placeholder="0.00" style="font-size:20px;font-weight:bold;" required>
                                </div>
                                <div class="form-group">
                                    <label>Payment Method</label>
                                    <select name="payment_method" class="form-control">
                                        <option value="cash">Cash</option>
                                        <option value="mobile_money">Mobile Money</option>
                                        <option value="bank_transfer">Bank Transfer</option>
                                        <option value="credit_card">Credit Card</option>
                                        <option value="insurance">Insurance</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Notes</label>
                                    <textarea name="notes" class="form-control" rows="2" placeholder="Payment notes (optional)"></textarea>
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
                <script>
                function validatePaymentForm(){
                    var amt = parseFloat(document.getElementById('pm_amount').value);
                    if (isNaN(amt) || amt <= 0) {
                        alert('Please enter a valid payment amount.');
                        return false;
                    }
                    return confirm('Record payment of ' + amt.toFixed(2) + '?');
                }
                </script>
                <?php } ?>
            </aside><!-- /.right-side -->
        </div><!-- ./wrapper -->
  
        
         <script src="<?php echo base_url();?>public/js/jquery.min.js"></script>
         <script src="<?php echo base_url();?>public/js/bootstrap.min.js" type="text/javascript"></script>     
        <script src="<?php echo base_url();?>public/js/AdminLTE/app.js" type="text/javascript"></script>
        
         <!-- BDAY -->
         <script src="<?php echo base_url();?>public/datepicker/js/jquery-1.9.1.min.js"></script>
        <script src="<?php echo base_url();?>public/datepicker/js/bootstrap-datepicker.js"></script>
        <script type="text/javascript">
            // When the document is ready
            $(document).ready(function () {
                
                $('#cFrom').datepicker({
                    //format: "dd/mm/yyyy"
					format: "yyyy-mm-dd"
                });  
				
				$('#cTo').datepicker({
                    //format: "dd/mm/yyyy"
					format: "yyyy-mm-dd"
                });  
            
            });
        </script>
        <!-- END BDAY -->
        
        
    </body>
</html>