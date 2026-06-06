<!DOCTYPE html>
<html>
    <head>

        <meta charset="UTF-8">
        <title>SALES INVOICE</title>
        
    </head>  
    <style>
    body{
		font-family:-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
		font-size:13px;
		line-height:1.35;
		color:#111827;
		margin:0;
		padding:24px;
		background:#f3f4f6;
	}
	.doc-card{
		max-width:900px;
		margin:0 auto;
		background:#fff;
		border:1px solid #e5e7eb;
		border-radius:12px;
		box-shadow:0 10px 30px rgba(0,0,0,0.06);
		padding:20px;
	}
	.header{
		text-align:center;
		padding-bottom:14px;
		border-bottom:1px solid #e5e7eb;
		margin-bottom:16px;
	}
	.header .brand{ font-size:18px; font-weight:800; }
	.header .muted{ color:#6b7280; }
	.header .title{ margin-top:10px; font-weight:800; letter-spacing:0.10em; font-size:12px; }
	.grid{ width:100%; border-collapse:collapse; margin:10px 0 14px 0; }
	.grid td{ vertical-align:top; padding:0 10px 0 0; }
	.box{ border:1px solid #e5e7eb; border-radius:10px; padding:12px; }
	.box h4{ margin:0 0 8px 0; font-size:12px; text-transform:uppercase; letter-spacing:0.06em; color:#374151; }
	.items{ width:100%; border-collapse:collapse; border:1px solid #e5e7eb; border-radius:12px; overflow:hidden; }
	.items th{ background:#f9fafb; text-align:left; font-size:11px; text-transform:uppercase; letter-spacing:0.06em; color:#374151; padding:10px; border-bottom:1px solid #e5e7eb; }
	.items td{ padding:9px 10px; border-bottom:1px solid #f1f5f9; }
	.items tr:last-child td{ border-bottom:none; }
	.num{ text-align:right; font-variant-numeric:tabular-nums; }
	.summary{ width:360px; max-width:100%; margin-left:auto; border-collapse:collapse; border:1px solid #e5e7eb; border-radius:12px; overflow:hidden; }
	.summary th{ background:#f9fafb; padding:9px 10px; text-align:left; border-bottom:1px solid #e5e7eb; color:#374151; }
	.summary td{ padding:9px 10px; text-align:right; border-bottom:1px solid #f1f5f9; font-variant-numeric:tabular-nums; }
	.summary tr:last-child th,
	.summary tr:last-child td{ border-bottom:none; }
	@media print{
		body{ background:#fff; padding:0; }
		.doc-card{ border:none; border-radius:0; box-shadow:none; padding:0; }
	}
    </style>
<body>
<div class="doc-card">
<section class="content invoice">
                	<div class="row">
                        <div class="col-xs-12">
                            
                            								<div class="header">
									<?php
										if(!$companyInfo->logo){
											$picture = "sample.jpg";
										}else{
											$picture = $companyInfo->logo;
										}
									?>
									<?php $logo_url = base_url('public/company_logo/'.$picture); ?>
									<?php if (!empty($picture)) { ?>
										<img src="<?php echo $logo_url; ?>" alt="logo" style="height:46px; max-width:160px; margin-bottom:8px;" />
									<?php } ?>
									<div class="brand"><?php echo $companyInfo->company_name;?></div>
									<div class="muted"><?php echo $companyInfo->company_address;?></div>
									<div class="muted"><?php echo $companyInfo->company_contactNo;?></div>
									<div class="title">SALES INVOICE</div>
								</div>
                                                       
                        </div><!-- /.col -->
                    </div>
                    
                    <br><br>
                    
                     <!-- info row -->
                    <div class="row invoice-info">
                    	
                        <table class="grid">
                            <tr>
								<td style="width:40%;" valign="top">
									<div class="box">
										<h4>Customer</h4>
										<div><strong><?php echo $patientInfo->name?></strong></div>
										<div class="muted"><strong><i>DOB</i></strong> <?php echo date("M d, Y", strtotime($patientInfo->birthday));?></div>
										<div class="muted"><?php echo $patientInfo->street?></div>
										<div class="muted"><?php echo $patientInfo->subd_brgy?></div>
										<div class="muted"><?php echo $patientInfo->province?></div>
										<div class="muted"><?php echo $patientInfo->phone_no?></div>
									</div>
								</td>
								<td style="width:35%;" valign="top">
									<div class="box">
										<h4>Remit Payment To</h4>
										<div><strong><?php echo $companyInfo->company_name;?></strong></div>
										<div class="muted"><?php echo $companyInfo->company_address;?></div>
										<div class="muted"><?php echo $companyInfo->company_contactNo;?></div>
									</div>
								</td>
								<td style="width:25%; padding-right:0;" valign="top">
									<div class="box">
										<h4>Invoice</h4>
										<div><strong>No:</strong> <?php echo $headerInv->invoice_no?></div>
										<div><strong>Date:</strong> <?php echo date("M d, Y", strtotime($headerInv->dDate));?></div>
									</div>
								</td>
							</tr>
						</table>
                      
                            
                        </div><!-- /.col -->
                        
                    </div><!-- /.row -->
                    
                    <br>
                    
                    <!-- Table row -->
                    <div class="row">
                        <div class="col-xs-12 table-responsive">
                            						<table class="items">
                                <thead>
                                    <tr>
                                        <th width="34%">Particular Name</th>
                                        <th width="8%">Qty</th>
                                        <th width="8%">Rate</th>
                                        <th width="16%">Amount</th>
                                        <th width="34%">Note</th>
                                    </tr>                                    
                                </thead>
                                <tbody>
                                <?php 
								foreach($detailsInv as $detailsInv){
								if($detailsInv->isPackage == "1"){ 
									
									//get surgical package item list
									$ci_obj = & get_instance();
									$ci_obj->load->model('app/general_model');
									$surgical_items = $ci_obj->general_model->getSurgeryItems2($detailsInv->iop_id);
								?>
								<tr>
                                	<td colspan="5">
                                    	<table cellpadding="3" cellspacing="3" width="100%">
                                        <tr>
                                        	<td>&nbsp;&nbsp;&nbsp;&nbsp;<b><?php echo $detailsInv->bill_name?></b></td>
                                        </tr>
                                        <tr>
                                        	<td>
                                            	<table cellpadding="2" cellspacing="2" width="100%">
                                                <?php foreach($surgical_items as $surgical_items){?>
                                                <Tr>
                                                	<td width="50%">&nbsp;&nbsp;&nbsp;&nbsp;<?php echo $surgical_items->particular_name?></td>
                                                    <td width="19%" align="right"><?php echo number_format($surgical_items->costs,2)?>&nbsp;&nbsp;&nbsp;&nbsp;</td>
                                                    <td width="31%"><?php echo $surgical_items->cDesc?></td>
                                                </Tr>
                                                <?php }?>
                                                </table>
                                            </td>
                                        </tr>
                                        </table>
                                    </td>
                                </tr>	
								<?php }else{?>
                                <tr>
                                	<td>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<?php echo $detailsInv->bill_name?></td>
                                	<td class="num"><?php echo number_format($detailsInv->qty,2);?>&nbsp;</td>
								<td class="num"><?php echo $detailsInv->rate?>&nbsp;</td>
								<td class="num"><?php echo $detailsInv->amount?>&nbsp;</td>
								<td><?php echo $detailsInv->note?></td>
                                </tr>
                                <?php }}?>
                                </tbody>
                            </table>                            
                        </div><!-- /.col -->
                    </div><!-- /.row -->
                    
                    <div class="row">
                        <!-- accepted payments column -->
                        <div class="col-xs-6">
                            <p class="lead">Note:</p>
                            <p class="text-muted well well-sm no-shadow" style="margin-top: 10px;">
                                <i>Amount shown are in GHS currency.</i>
                            </p>
                        </div><!-- /.col -->
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
                                        <th>Total Amount:</th>
                                        <td><?php echo number_format($headerInv->total_amount,2)?></td>
                                    </tr>
                                    <?php
                                        $pi_payer = isset($headerInv->payer_type) ? strtoupper(trim((string)$headerInv->payer_type)) : '';
                                        if ($pi_payer === 'NHIS'):
                                            $pi_cov = isset($headerInv->nhis_covered_amount) ? (float)$headerInv->nhis_covered_amount : 0;
                                            $pi_pay = isset($headerInv->patient_payable_amount) ? (float)$headerInv->patient_payable_amount : 0;
                                    ?>
                                    <tr>
                                        <th style="color:#27ae60;">NHIS Covered:</th>
                                        <td style="color:#27ae60;font-weight:bold;"><?php echo number_format($pi_cov, 2); ?></td>
                                    </tr>
                                    <tr>
                                        <th style="color:#e74c3c;">Patient Pays:</th>
                                        <td style="color:#e74c3c;font-weight:bold;"><?php echo number_format($pi_pay, 2); ?></td>
                                    </tr>
                                    <?php endif; ?>
                                </table>
                            </div>
                        						</div><!-- /.col -->
                    </div><!-- /.row -->
                    
                    
                    
                </section>
</div>
</body>
</html>