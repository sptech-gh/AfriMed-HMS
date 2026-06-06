<style>
    @page { margin: 0; }
    body{
        font-family: DejaVu Sans, Arial, sans-serif;
        font-size: 11px;
        color: #111827;
        margin: 0;
        padding: 0;
    }
    .receipt-card{
        border:1px solid #e5e7eb;
        padding:12px;
        width: 80mm;
        margin: 0 auto;
    }
    .receipt-header{
        text-align:center;
        border-bottom:1px solid #e5e7eb;
        padding-bottom:10px;
        margin-bottom:12px;
    }
    .brand{ font-size:16px; font-weight:bold; }
    .muted{ color:#6b7280; }
    .title{ margin-top:8px; font-weight:bold; letter-spacing:2px; font-size:12px; }
    .meta{ margin-top:6px; font-size:10px; }
    .meta-row{ padding:2px 0; }
    .badge{ display:inline-block; border:1px solid #e5e7eb; padding:3px 6px; border-radius:999px; font-size:9px; letter-spacing:1px; text-transform:uppercase; }
    .paid-stamp{ display:inline-block; border:2px solid #16a34a; color:#16a34a; padding:3px 8px; border-radius:8px; font-weight:bold; letter-spacing:2px; margin-top:6px; }
    .grid{ width:100%; border-collapse:collapse; margin-bottom:10px; }
    .box{ border:1px solid #e5e7eb; padding:10px; }
    .box h4{ margin:0 0 6px 0; font-size:11px; text-transform:uppercase; letter-spacing:1px; color:#374151; }
    .items{ width:100%; border-collapse:collapse; border:1px solid #e5e7eb; }
    .items th{ background:#f9fafb; border-bottom:1px solid #e5e7eb; padding:8px; font-size:10px; text-transform:uppercase; letter-spacing:1px; text-align:left; }
    .items td{ border-bottom:1px solid #f1f5f9; padding:7px 8px; }
    .num{ text-align:right; }
    .summary{ width:100%; margin-left:auto; border-collapse:collapse; border:1px solid #e5e7eb; margin-top:10px; }
    .summary th{ background:#f9fafb; border-bottom:1px solid #e5e7eb; padding:7px 8px; text-align:left; }
    .summary td{ border-bottom:1px solid #f1f5f9; padding:7px 8px; text-align:right; }
</style>

<div class="row">
                  	<div class="col-md-12">

            <!-- Right side column. Contains the navbar and content of the page -->
                    

 	              	<section class="content invoice">
						<div class="receipt-card">
	                	
									<center>
                    <?php
														if(!$companyInfo->logo){
																$picture = "sample.jpg";	
														}else{
																$picture = $companyInfo->logo;
														}
													?>
										
                    														<div class="receipt-header">
											<div class="brand"><?php echo $companyInfo->company_name;?></div>
											<div class="muted"><?php echo $companyInfo->company_address;?></div>
											<div class="muted">Contact: <?php echo $companyInfo->company_contactNo;?> &nbsp; | &nbsp; TIN: <?php echo $companyInfo->TIN;?></div>
											<div class="title">RECEIPT</div>
											<div class="meta">
												<div class="meta-row"><span class="muted">Receipt No:</span> <strong><?php echo $getOR->receipt_no?></strong></div>
												<div class="meta-row"><span class="muted">Invoice No:</span> <strong><?php echo $headerInv->invoice_no?></strong></div>
												<div class="meta-row"><span class="muted">Date:</span> <strong><?php echo date("M d, Y H:i", strtotime($getOR->dDate));?></strong></div>
												<?php if (!empty($receipt_payment_method_label)) { ?><div class="meta-row"><span class="badge"><?php echo htmlspecialchars((string)$receipt_payment_method_label); ?></span></div><?php } ?>
												<?php if (!empty($receipt_cashier_name) || !empty($receipt_cashier_id)) { ?><div class="meta-row"><span class="muted">Cashier:</span> <strong><?php echo htmlspecialchars((string)(!empty($receipt_cashier_name) ? $receipt_cashier_name : $receipt_cashier_id)); ?></strong></div><?php } ?>
												<?php if (isset($receipt_outstanding_balance) && (float)$receipt_outstanding_balance <= 0.0001) { ?><div class="paid-stamp">PAID</div><?php } ?>
											</div>
										</div>
														</center><br>
                     <!-- info row -->
                    <div class="row invoice-info">
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
                                    <div style="font-weight:bold;"><?php echo $detailsInv->bill_name?></div>
                                    <div class="muted" style="font-size:9px; margin-top:2px;">
                                        <?php echo number_format((float)$detailsInv->qty,2); ?> x <?php echo number_format((float)$detailsInv->rate,2); ?>
                                        <?php if (trim((string)$detailsInv->note) !== '') { ?> • <?php echo $detailsInv->note; ?><?php } ?>
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
                                    <tr>
                                        <th>Previous Balance:</th>
                                        <td><?php echo number_format(isset($receipt_prev_balance) ? (float)$receipt_prev_balance : 0,2)?></td>
                                    </tr>
                                    <tr>
                                        <th>Payment (This Receipt):</th>
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
