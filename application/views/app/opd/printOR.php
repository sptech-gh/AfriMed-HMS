 <style>
 	*{ box-sizing:border-box; }
    body{
		font-family:-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
		font-size:12px;
		line-height:1.35;
		color:#111827;
		margin:0;
		padding:12px;
		background:#f3f4f6;
	}
	.receipt-card{
		width:80mm;
		max-width:80mm;
		margin:0 auto;
		background:#fff;
		border:1px solid #e5e7eb;
		border-radius:12px;
		box-shadow:0 8px 24px rgba(0,0,0,0.08);
		padding:14px;
	}
	.receipt-header{
		display:block;
		text-align:center;
		padding-bottom:10px;
		border-bottom:1px solid #e5e7eb;
		margin-bottom:10px;
	}
	.receipt-header .brand{
		font-size:14px;
		font-weight:700;
		margin:0;
	}
	.receipt-header .muted{ color:#6b7280; }
	.receipt-title{
		margin-top:8px;
		font-weight:800;
		letter-spacing:0.10em;
		font-size:11px;
		color:#111827;
	}
	.meta{ margin-top:8px; font-size:11px; }
	.meta-row{ display:block; padding:2px 0; }
	.kv{ display:block; }
	.k{ color:#6b7280; }
	.v{ font-weight:700; }
	.badge{
		display:inline-block;
		padding:4px 8px;
		border:1px solid #e5e7eb;
		border-radius:999px;
		font-size:10px;
		letter-spacing:0.08em;
		text-transform:uppercase;
		color:#111827;
		background:#f9fafb;
		margin-top:6px;
	}
	.paid-stamp{
		display:inline-block;
		border:2px solid #16a34a;
		color:#16a34a;
		padding:4px 10px;
		border-radius:10px;
		font-weight:800;
		letter-spacing:0.14em;
		font-size:12px;
		margin-top:8px;
	}
	.receipt-grid{ width:100%; border-collapse:collapse; margin:10px 0 10px 0; }
	.receipt-grid td{ vertical-align:top; padding:0; }
	.receipt-grid .box{
		border:1px solid #e5e7eb;
		border-radius:10px;
		padding:12px;
	}
	.receipt-grid h4{ margin:0 0 8px 0; font-size:12px; text-transform:uppercase; letter-spacing:0.06em; color:#374151; }
	.items{
		width:100%;
		border-collapse:collapse;
		border:1px solid #e5e7eb;
		border-radius:12px;
		overflow:hidden;
		margin-top:10px;
	}
	.items th{
		background:#f9fafb;
		text-align:left;
		font-size:11px;
		text-transform:uppercase;
		letter-spacing:0.06em;
		color:#374151;
		padding:10px 10px;
		border-bottom:1px solid #e5e7eb;
	}
	.items td{
		padding:9px 10px;
		border-bottom:1px solid #f1f5f9;
	}
	.items tr:last-child td{ border-bottom:none; }
	.items td.num{ text-align:right; font-variant-numeric:tabular-nums; }
	.summary-wrap{ margin-top:14px; }
	.summary{
		width:100%;
		max-width:100%;
		margin-left:auto;
		border-collapse:collapse;
		border:1px solid #e5e7eb;
		border-radius:12px;
		overflow:hidden;
	}
	.summary th{
		text-align:left;
		padding:9px 10px;
		background:#f9fafb;
		border-bottom:1px solid #e5e7eb;
		color:#374151;
		font-weight:700;
	}
	.summary td{
		text-align:right;
		padding:9px 10px;
		border-bottom:1px solid #f1f5f9;
		font-variant-numeric:tabular-nums;
	}
	.summary tr:last-child th,
	.summary tr:last-child td{ border-bottom:none; }
	@media print{
		@page{ size:80mm auto; margin:0; }
		body{ background:#fff; padding:0; }
		.receipt-card{ border:none; border-radius:0; box-shadow:none; padding:0; }
	}
    </style>
<title>RECEIPT</title>
<div class="row">
                 	<div class="col-md-12">

            <!-- Right side column. Contains the navbar and content of the page -->
                    

	              	<section class="content invoice">
						
												<div class="receipt-card">
									
														<div class="receipt-header">
												<?php
																		if(!$companyInfo->logo){
																			$picture = "sample.jpg";	
																		}else{
																			$picture = $companyInfo->logo;
																		}
																	?>
														<?php $logo_url = base_url('public/company_logo/'.$picture); ?>
														<?php if (!empty($picture)) { ?>
																<img src="<?php echo $logo_url; ?>" alt="logo" style="height:40px; max-width:120px; margin-bottom:6px;" />
														<?php } ?>
														<div class="brand"><?php echo $companyInfo->company_name;?></div>
														<div class="muted"><?php echo $companyInfo->company_address;?></div>
														<div class="muted">Contact: <?php echo $companyInfo->company_contactNo;?> &nbsp;&nbsp; | &nbsp;&nbsp; TIN: <?php echo $companyInfo->TIN;?></div>
														<div class="receipt-title">RECEIPT</div>
														<div class="meta">
															<div class="meta-row"><span class="k">Receipt No:</span> <span class="v"><?php echo $getOR ? $getOR->receipt_no : ''; ?></span></div>
															<div class="meta-row"><span class="k">Invoice No:</span> <span class="v"><?php echo $headerInv ? $headerInv->invoice_no : ''; ?></span></div>
															<div class="meta-row"><span class="k">Date:</span> <span class="v"><?php echo ($getOR && !empty($getOR->dDate)) ? date("M d, Y H:i", strtotime($getOR->dDate)) : ''; ?></span></div>
															<?php if (!empty($receipt_payment_method_label)) { ?>
																<div class="meta-row"><span class="badge"><?php echo htmlspecialchars((string)$receipt_payment_method_label); ?></span></div>
															<?php } ?>
															<?php if (!empty($receipt_cashier_name) || !empty($receipt_cashier_id)) { ?>
																<div class="meta-row"><span class="k">Cashier:</span> <span class="v"><?php echo htmlspecialchars((string)(!empty($receipt_cashier_name) ? $receipt_cashier_name : $receipt_cashier_id)); ?></span></div>
															<?php } ?>
															<?php if (isset($receipt_outstanding_balance) && (float)$receipt_outstanding_balance <= 0.0001) { ?>
																<div class="paid-stamp">PAID</div>
															<?php } ?>
														</div>
													</div>
										 <!-- info row -->
										<div class="row invoice-info" style="margin-top:10px;">
											<div class="box">
												<h4>Customer</h4>
												<?php
												$receiptCustomer = (isset($patientInfo) && is_object($patientInfo)) ? $patientInfo : (object)array('name' => 'Walk-in Client', 'street' => '', 'subd_brgy' => '', 'province' => '', 'phone_no' => '');
												?>
												<div><strong><?php echo htmlspecialchars(isset($receiptCustomer->name) ? (string)$receiptCustomer->name : 'Walk-in Client'); ?></strong></div>
												<div class="muted"><?php echo htmlspecialchars(isset($receiptCustomer->street) ? (string)$receiptCustomer->street : ''); ?></div>
												<div class="muted"><?php echo htmlspecialchars(isset($receiptCustomer->subd_brgy) ? (string)$receiptCustomer->subd_brgy : ''); ?></div>
												<div class="muted"><?php echo htmlspecialchars(isset($receiptCustomer->province) ? (string)$receiptCustomer->province : ''); ?></div>
												<div class="muted"><?php echo htmlspecialchars(isset($receiptCustomer->phone_no) ? (string)$receiptCustomer->phone_no : ''); ?></div>
											</div>
										</div><!-- /.row -->
                    
                    
                    <!-- Table row -->
                    <div class="row">
                        <div class="col-xs-12 table-responsive">
                            <table class="items">
                                <thead>
                                    <tr>
                                        <th style="width:72%">Item</th>
                                        <th style="width:28%">Total</th>
                                    </tr>														
                                </thead>
                                <tbody>
                                <?php foreach($detailsInv as $detailsInv){?>
                                <tr>
                                <td>
															<div style="font-weight:600;"><?php echo $detailsInv->bill_name?></div>
															<div class="muted" style="font-size:10px; margin-top:2px;">
																<?php echo number_format((float)$detailsInv->qty,2); ?> x <?php echo number_format((float)$detailsInv->rate,2); ?>
																<?php if (trim((string)$detailsInv->note) !== '') { ?>
																	 • <?php echo $detailsInv->note; ?>
																<?php } ?>
															</div>
														</td>
                                <td class="num"><?php echo number_format((float)$detailsInv->amount, 2); ?></td>
                                </tr>
                                <?php }?>
                                </tbody>
                            </table>							
                        </div><!-- /.col -->
                    </div><!-- /.row -->
                    
                    <div class="row">
                        <div class="col-xs-6">
                            <p class="lead">Amount Details</p>
                            <div class="table-responsive">
                                <table class="summary">
                                    <tr>
                                        <th style="width:50%">Subtotal:</th>
                                        <td><?php echo number_format($headerInv->sub_total,2)?></td>
                                    </tr>
                                    <tr>
                                        <th style="width:50%">Discount:</th>
                                        <td><?php echo number_format($headerInv->discount,2)?></td>
                                    </tr>
                                    <tr>
                                        <th>Total:</th>
                                        <td><?php echo number_format($headerInv->total_amount,2)?></td>
                                    </tr>
                                </table>
                            </div>
                        </div><!-- /.col -->
                    </div><!-- /.row -->
                    
                    <div class="row">
                        <div class="col-xs-6">
                            <p class="lead">Payment Details</p>
                            <div class="table-responsive">
                                <table class="summary">
                                    <tr>
                                        <th style="width:60%">Previous Balance:</th>
                                        <td><?php echo number_format(isset($receipt_prev_balance) ? (float)$receipt_prev_balance : 0,2)?></td>
                                    </tr>
                                    <tr>
                                        <th style="width:60%">Payment (This Receipt):</th>
                                        <td><?php echo number_format(isset($receipt_payment) ? (float)$receipt_payment : (float)$getOR->amountPaid,2)?></td>
                                    </tr>
                                    <tr>
                                        <th>Total Paid (To Date):</th>
                                        <td><?php echo number_format(isset($receipt_total_paid) ? (float)$receipt_total_paid : 0,2)?></td>
                                    </tr>
                                    <tr>
                                        <th>Outstanding Balance:</th>
                                        <td><?php echo number_format(isset($receipt_outstanding_balance) ? (float)$receipt_outstanding_balance : 0,2)?></td>
                                    </tr>
                                    <tr>
                                        <th>Amount Tendered:</th>
                                        <td><?php echo number_format(isset($receipt_amount_tendered) ? (float)$receipt_amount_tendered : ((float)$getOR->amountPaid + max(0, (float)$getOR->change)),2)?></td>
                                    </tr>
                                    <tr>
                                        <th>Change:</th>
                                        <td><?php echo number_format(max(0, (float)$getOR->change),2)?></td>
                                    </tr>
                                </table>
                            </div>
                        </div><!-- /.col -->
                    </div><!-- /.row -->
                    
                    
                </div>
                </section>
               
                </div>
                 </div>
       
  
        <!-- END BDAY -->
