<!DOCTYPE html>
<html>
    <head>

        <meta charset="UTF-8">
        <title></title>
        
    </head>  
    <style>
    body{
		font-family:Verdana, Geneva, sans-serif;
		font-size:9;
	}
    </style>
<body>
<section class="content invoice">
                	<div class="row">
                        <div class="col-xs-12">
                            <h2 class="page-header">
                            			<?php
											if(!$companyInfo->logo){
												$picture = "sample.jpg";	
											}else{
												$picture = $companyInfo->logo;
											}
										?>
										<img src="<?php echo base_url();?>public/company_logo/<?php echo $picture;?>" class="img-rounded" width="120" height="120">
                                 <?php echo $companyInfo->company_name;?>
                                
                            </h2>                            
                        </div><!-- /.col -->
                    </div>
                    
                    <br><br>
                    
                     <!-- info row -->
                    <div class="row invoice-info">
                    	
                        <table width="100%">
                        <tr>
                        	<td>
                            	<strong><?php echo $patientInfo->name?></strong> <strong><br><i>DOB</i> </strong><?php echo date("M d, Y", strtotime($patientInfo->birthday));?><Br>
                                <?php echo $patientInfo->street?><br>
                                <?php echo $patientInfo->subd_brgy?><br>
                                <?php echo $patientInfo->province?><br>
                                <?php echo $patientInfo->phone_no?><br>
                            </td>
                            <td valign="top">
                            	 Remit Payment To<br>
                               <address>
                                <strong> <?php echo $companyInfo->company_name;?></strong><br>
                                 <?php echo $companyInfo->company_address;?><br>
                                  <?php echo $companyInfo->company_contactNo;?>
                            </address>
                            </td>
                            <td valign="top">
                            	<b>Invoice:</b> <?php echo $headerInv->invoice_no?><br/>
                                <b>Date:</b> <?php echo date("M d, Y", strtotime($headerInv->dDate));?>
                                </td>
                        </tr>
                        </table>
                      
                            
                        </div><!-- /.col -->
                        
                    </div><!-- /.row -->
                    
                    <br>
                    
                    <!-- Table row -->
                    <div class="row">
                        <div class="col-xs-12 table-responsive">
                            <table width="100%" cellpadding="2" cellspacing="2" border="1" style="border:1px; border-collapse:collapse;">
                                <thead>
                                    <tr>
                                        <th>Particular Name</th>
                                        <th>Qty</th>
                                        <th>Rate</th>
                                        <th>Amount</th>
                                        <th>Note</th>
                                    </tr>                                    
                                </thead>
                                <tbody>
                                <?php foreach($detailsInv as $detailsInv){?>
                                <tr>
                                <td><?php echo $detailsInv->bill_name?></td>
                                <td align="right"><?php echo $detailsInv->qty?>&nbsp;</td>
                                <td align="right"><?php echo $detailsInv->rate?>&nbsp;</td>
                                <td align="right"><?php echo $detailsInv->amount?>&nbsp;</td>
                                <td><?php echo $detailsInv->note?></td>
                                </tr>
                                <?php }?>
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
                                <table class="table">
                                    <tr>
                                        <th style="width:50%">Subtotal:</th>
                                        <td><?php echo number_format($headerInv->total_amount,2)?></td>
                                    </tr>
                                    <tr>
                                        <th>Total:</th>
                                        <td><?php echo number_format($headerInv->total_amount,2)?></td>
                                    </tr>
                                </table>
                            </div>
                        </div><!-- /.col -->
                    </div><!-- /.row -->
                    
                    
                    
                </section>
</body>
</html>