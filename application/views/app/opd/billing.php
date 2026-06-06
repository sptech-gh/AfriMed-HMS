<!DOCTYPE html>
<html>
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
                    <h1>OPD Patient Information</h1>
                   <ol class="breadcrumb">
                        <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                        <li><a href="#">Patient Management</a></li>
                        <li><a href="<?php echo base_url()?>app/opd/index">OPD</a></li>
                        <li><a href="<?php echo base_url()?>app/opd/index">Out-Patient Master</a></li>
                        <li class="active">OPD Patient Information</li>
                    </ol>
                </section>

                <!-- Main content -->
                <section class="content">
                 
        
                 
                 
               
                 <div class="row">
                 	
                     <div class="col-md-3">
                    	 <div class="box">
                         	 <div class="box-header"></div>
                        	<div class="box-body table-responsive no-padding">
                            	<table width="100%" cellpadding="3" cellspacing="3">
                                <tr>
                                	<td width="15%" valign="top" align="center">
                                    <?php
									if(!$patientInfo->picture){
										$picture = "avatar.png";	
									}else{
										$picture = $patientInfo->picture;
									}
									?>
									<img src="<?php echo base_url();?>public/patient_picture/<?php echo $picture;?>" class="img-rounded" width="86" height="81">
                                    </td>
                                    <td>
                                    	<table width="100%">
                                        <tr>
                                        	<td><u>Patient No.</u></td>
                                        </tr>
                                        <tr>
                                			<td><?php echo $patientInfo->patient_no?></td>
                                		</tr>
                                        <tr>
                                        	<td><u>Patient Name</u></td>
                                        </tr>
                                        <tr>
                                			<td><?php echo $patientInfo->name?></td>
                                		</tr>
                                        <?php
                                            $b_nhis = isset($patientInfo->nhis_number) ? trim((string)$patientInfo->nhis_number) : '';
                                            $b_nhis_st = isset($patientInfo->nhis_status) ? strtoupper(trim((string)$patientInfo->nhis_status)) : '';
                                            if ($b_nhis !== ''):
                                        ?>
                                        <tr>
                                            <td>
                                                <?php if ($b_nhis_st === 'ACTIVE'): ?>
                                                    <span class="label label-success"><i class="fa fa-check-circle"></i> NHIS Active</span>
                                                <?php elseif ($b_nhis_st === 'EXPIRED'): ?>
                                                    <span class="label label-danger"><i class="fa fa-exclamation-triangle"></i> NHIS Expired</span>
                                                <?php else: ?>
                                                    <span class="label label-default">NHIS</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                        </table>
                                    </td>
                                </tr>
                                </table>
                            </div>
                            <div class="box-footer clearfix">
                            	<div style="margin-top: 15px;">
                                 <ul class="nav nav-pills nav-stacked">
                                 	<li><a href="<?php echo base_url()?>app/opd/view/<?php echo url_safe_id($getOPDPatient->IO_ID);?>/<?php echo $getOPDPatient->patient_no;?>"> General Information</a></li>
                                
                                 	<li><a href="<?php echo base_url()?>app/opd/diagnosis/<?php echo url_safe_id($getOPDPatient->IO_ID);?>/<?php echo $getOPDPatient->patient_no;?>"> Diagnosis</a></li>
                                 	
                                 	<li><a href="<?php echo base_url()?>app/opd/medication/<?php echo url_safe_id($getOPDPatient->IO_ID);?>/<?php echo $getOPDPatient->patient_no;?>"> Medication</a></li>
                                    <li><a href="<?php echo base_url()?>app/opd/complain/<?php echo url_safe_id($getOPDPatient->IO_ID);?>/<?php echo $getOPDPatient->patient_no;?>"> Complain</a></li>
                                    <li><a href="<?php echo base_url()?>app/opd/vitalSign/<?php echo url_safe_id($getOPDPatient->IO_ID);?>/<?php echo $getOPDPatient->patient_no;?>"> Vital Sign</a></li>
                                    <li><a href="<?php echo base_url()?>app/opd/patientHistory/<?php echo url_safe_id($getOPDPatient->IO_ID);?>/<?php echo $getOPDPatient->patient_no;?>"> Patient History</a></li>
                                 	<li class="active"><a href="<?php echo base_url()?>app/opd/billing/<?php echo url_safe_id($getOPDPatient->IO_ID);?>/<?php echo $getOPDPatient->patient_no;?>"> Admission Billing</a></li>
                                 </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                     
                     <?php echo form_open('app/billing/save_invoice', array('onsubmit' => 'return validate_form()')); ?>
                     <input type="hidden" name="opd_no" value="<?php echo $getOPDPatient->IO_ID?>">
                     <input type="hidden" name="patient_no" value="<?php echo $patientInfo->patient_no?>">
                     <div class="col-md-9"> 
                                <div class="nav-tabs-custom">
                                	<ul class="nav nav-tabs">
                                		<li class="active"><a href="#tab_1" data-toggle="tab">Admission Billing</a></li>
                                        <li><a href="#tab_2" data-toggle="tab">Header Details</a></li>
                                	</ul>
                                    <div class="tab-content">
                                    	<div class="tab-pane" id="tab_2">
                                        
                                        		<script>
								function setTitle(val){
									var stEl = document.getElementById('insurance_card_status');
									var st = stEl ? (stEl.value || '').toString().toUpperCase().trim() : 'ACTIVE';
									if (val == "insurance" && st === 'INACTIVE') {
										alert('Insurance card is inactive. Please use Cash or Credit.');
										document.getElementById("paymentType").value = 'cash';
										val = 'cash';
									}
									if(val == "cash"){
										document.getElementById("credit").style.display = "none";
										document.getElementById("insurance").style.display = "none";	
									}else if(val == "credit"){
										document.getElementById("credit").style.display = "inline";
										document.getElementById("insurance").style.display = "none";	
									}else if(val == "insurance"){
										document.getElementById("credit").style.display = "none";
										document.getElementById("insurance").style.display = "inline";	
									}
								}
								</script>
								<?php
								$insCardSt = isset($patientInfo->insurance_card_status) ? strtoupper(trim((string)$patientInfo->insurance_card_status)) : 'ACTIVE';
								if ($insCardSt !== 'ACTIVE' && $insCardSt !== 'INACTIVE') { $insCardSt = 'ACTIVE'; }
								?>
								<input type="hidden" id="insurance_card_status" value="<?php echo $insCardSt; ?>">
								<?php if ($insCardSt === 'INACTIVE'): ?>
									<div class="alert alert-warning" style="width:60%;">
										Insurance card is inactive. Billing will be treated as Cash/Credit.
									</div>
								<?php endif; ?>
                                                </script>
                                        
                                        		<?php
													$userID = $invoice_no->invoice_no;
													$userID2 = $invoice_no->invoice_no;
													if(strlen($userID) == 1){
														$userID = "00000".$userID;
													}else if(strlen($userID) == 2){
														$userID = "0000".$userID;
													}else if(strlen($userID) == 3){
														$userID = "000".$userID;
													}else if(strlen($userID) == 4){
														$userID = "00".$userID;
													}else if(strlen($userID) == 5){
														$userID = "0".$userID;
													}else if(strlen($userID) == 6){
														$userID = $userID;
													}
													?>
                                               
                                               
                                               <div class="form-group">
                                            		<label for="exampleInputEmail1">Date</label>
                                            		 <input type="text" value="<?php echo date("M d, Y")?>" name="invoice_date" id="invoice_date" class="form-control input-sm" style="width:250px;" readonly>
                                        		</div>

                                                <div class="form-group">
                                            		<label for="exampleInputEmail1">Invoice No.</label>
                                            		 <input type="text" value="SI-<?php echo $userID;?>" name="invoiceno" id="invoiceno" class="form-control input-sm" style="width:250px;" readonly>
                                                    <input type="hidden" value="<?php echo $userID2;?>" name="invoiceno2" id="invoiceno2" class="form-control input-sm" style="width:250px;" readonly>
                                        		</div>
                                                
                                                <div class="form-group">
                                            		<label for="exampleInputEmail1">Mode of Payment</label>
                                            		<select name="paymentType" id="paymentType" class="form-control input-sm" style="width:250px;" onChange="setTitle(this.value)">
                                                    	<option value="">- Mode of Payment -</option>
                                                        <option value="cash">Cash</option>
                                                        <option value="credit">Credit</option>
                                                        <option value="insurance" <?php echo ($insCardSt === 'INACTIVE') ? 'disabled' : ''; ?>>Insurance Company</option>
                                                    </select>
                                        		</div>
                                               
                                               <div class="form-group" id="credit" style=" display:none;">
                                            		<label for="exampleInputEmail1">Credit Card No.</label>
                                            		 <input type="text" placeholder="Credit Card No." name="creditCardNo" id="creditCardNo" class="form-control input-sm" style="width:250px;">
                                        		</div>
                                                
                                                <div class="form-group" id="insurance" style=" display:none;">
                                            		<label for="exampleInputEmail1">Insurance Company</label>
                                            		<select name="insurance_company" id="insurance_company" class="form-control input-sm" style="width:250px;">
                                                    	<option value="">- Insurance Company -</option>
                                                        <?php foreach($insurance_company as $insurance_company){?>
                                                        <option value="<?php echo $insurance_company->in_com_id;?>"><?php echo $insurance_company->company_name;?></option>
                                                        <?php }?>
                                                    </select>
                                        		</div>
                                                
                                                <div class="form-group" id="credit">
                                            		<label for="exampleInputEmail1">Remarks</label>
                                            		<textarea placeholder="Remarks" class="form-control input-sm" style="width:60%; height:100px;" name="remarks" id="remarks"></textarea>
                                        		</div>
                                                
                                               
                                        </div>
                                        <div class="tab-pane active" id="tab_1">
                                        	
                                            <?php echo $message;?>
                                        	
                                           <div class="alt2" dir="ltr" style="
											margin: 0px;
											padding: 0px;
											border: 0px solid #919b9c;
											width: 100%;
											height: 350px;
											text-align: left;
											overflow: auto">
                                			<table id="myTable" width="100%" cellpadding="2" cellspacing="2">
                                    		<thead>
                                    			<tr>
                                        			<th width="3%">No.</th>
                                            		<th width="42%">Particular Name</th>
                                            		<th width="7%">Qty</th>
                                            		<th width="10%">Rate</th>
                                            		<th width="10%">Amount</th>
                                            		<th width="25%">Note</th>
                                            		<th width="3%"></th>
                                        		</tr>
                                    		</thead>
                                    		<tbody>
                                    		</tbody>
                                    		</table>
                                            <input type="hidden" name="hdnrowcnt" id="hdnrowcnt" value="0">
                                    		</div>
                                            
                                            
                                        </div>
                           			</div>
                            <div class="box-footer clearfix">
                               	<table width="100%" cellpadding="2" cellspacing="2">
                                <tr>
                                	<td> 
                                    <button class="btn btn-primary" name="btnSubmit" id="btnSubmit" type="submit"><i class="fa fa-save"></i> Save</button>
                                    <button class="btn btn-default" name="btnSubmit" id="btnSubmit" type="button" data-toggle="modal" data-target="#myModal"><i class="fa fa-plus"></i> Add Item</button>
                                    </td>
                                    <td align="right">GROSS&nbsp;&nbsp;
                                    <input type="text" name="nGross" id="nGross" readonly style="width:100px;">
                                    <input type="hidden" name="total_amount" id="total_amount"></td>
                                </tr>
                                <?php
                                    $bill_payer = isset($patientInfo->nhis_status) && strtoupper(trim((string)$patientInfo->nhis_status)) === 'ACTIVE' ? 'NHIS' : 'CASH';
                                ?>
                                <input type="hidden" name="payer_type" id="payer_type" value="<?php echo $bill_payer; ?>">
                                <?php if ($bill_payer === 'NHIS'): ?>
                                <tr>
                                    <td>
                                        <span class="label label-success"><i class="fa fa-medkit"></i> NHIS Patient</span>
                                    </td>
                                    <td align="right">
                                        <span style="color:#27ae60;font-weight:bold;">NHIS Covered:&nbsp;</span>
                                        <input type="text" name="nhis_covered_display" id="nhis_covered_display" readonly style="width:100px;background:#eafaf1;color:#27ae60;font-weight:bold;border:1px solid #27ae60;" value="0.00">
                                        &nbsp;&nbsp;
                                        <span style="color:#e74c3c;font-weight:bold;">Patient Pays:&nbsp;</span>
                                        <input type="text" name="patient_pays_display" id="patient_pays_display" readonly style="width:100px;background:#fdedec;color:#e74c3c;font-weight:bold;border:1px solid #e74c3c;" value="0.00">
                                    </td>
                                </tr>
                                <?php endif; ?>
                                </table>
                            </div>
                        </div>
                    </div>
                 </div>
                 <?php echo form_close(); ?>
                 
                </section><!-- /.content -->
            </aside><!-- /.right-side -->
        </div><!-- ./wrapper -->
  
        
         <script src="<?php echo base_url();?>public/js/jquery.min.js"></script>
         <script src="<?php echo base_url();?>public/js/bootstrap.min.js" type="text/javascript"></script>     
        <script src="<?php echo base_url();?>public/js/AdminLTE/app.js" type="text/javascript"></script>
        
         <!-- BDAY -->
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
        
        
        <!-- Modal -->
                            <?php echo form_open('app/billing/save_invoice', array('onsubmit' => 'return validate_form()')); ?>
                            <div class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                                            <h4 class="modal-title" id="myModalLabel">Add Item</h4>
                                        </div>

<script language="javascript">
function showDrugName(category_id)
{
if (window.XMLHttpRequest)
  {
  xmlhttp=new XMLHttpRequest();
  }
else
  {// code for IE6, IE5
  xmlhttp=new ActiveXObject("Microsoft.XMLHTTP");
  }
xmlhttp.onreadystatechange=function()
  {
  if (xmlhttp.readyState==4 && xmlhttp.status==200)
    {
	
    document.getElementById("showCategories").innerHTML=xmlhttp.responseText;
    }
  }
  var supp;

xmlhttp.open("GET","<?php echo base_url();?>app/billing/getItem/"+category_id,true);
xmlhttp.send();

}

function getItemRate(category_id)
{
if (window.XMLHttpRequest)
  {
  xmlhttp2=new XMLHttpRequest();
  }
else
  {// code for IE6, IE5
  xmlhttp2=new ActiveXObject("Microsoft.XMLHTTP");
  }
xmlhttp2.onreadystatechange=function()
  {
  if (xmlhttp2.readyState==4 && xmlhttp2.status==200)
    {
	
    document.getElementById("showRate").innerHTML=xmlhttp2.responseText;
    }
  }

var _patNo = document.getElementById('patient_no') ? document.getElementById('patient_no').value : '';
xmlhttp2.open("GET","<?php echo base_url();?>app/billing/getRate/"+category_id+"?patient_no="+encodeURIComponent(_patNo),true);
xmlhttp2.send();

}

</script>
                                        <div class="modal-body">
                                        <table class="table table-striped">
                                        <tbody>
                                        <tr>
                                        	<td>Paricular Category</td>
                                            <td>
                                            				<select name="category" onChange="showDrugName(this.value);" id="category" class="form-control input-sm" style="width: 250px;" required>
                                                            	<option value="">- Paricular Category -</option>
																<?php 
																foreach($particular_cat as $particular_cat){?>
                                                            	<option value="<?php echo $particular_cat->group_id;?>"><?php echo $particular_cat->group_name;?></option>
                                                                <?php }?>
                                                            </select>
                                            </td>
                                        </tr>
                                        <tr>
                                        	<td>Paricular Item</td>
                                            <td>
                                            <span id="showCategories">
                        					<select name="item" id="item" class="form-control input-sm" style="width: 250px;" required>
                        						<option value="">- Paricular Item -</option>
                        					</select>
                                            </span>
                                            </td>
                                        </tr>
                                        <tr>
                                        	<td>Qty</td>
                                            <td><input type="text" onkeypress="return isNumberKey(event)" name="qty" id="qty" value="1" placeholder="Qty" class="form-control input-sm" style="width: 250px;" required></td>
                                        </tr>
                                        <tr>
                                        	<td>Rate</td>
                                            <td>
                                            <label id="showRate">
                                            <input type="text" onkeypress="return isNumberKey(event)" name="rate" id="rate" placeholder="rate" class="form-control input-sm" style="width: 250px;" required>
                                            </label>
                                            </td>
                                        </tr>
                                        <tr>
                                        	<td>Note</td>
                                            <td><textarea name="note" id="note" placeholder="note" class="form-control input-sm" style="width: 250px;"></textarea></td>
                                        </tr>
                                        </tbody>
                                        </table>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                                            <button type="button" class="btn btn-primary" onClick="return addItem()">Add</button>
                                        </div>
                                       
                                    </div>
                                    <!-- /.modal-content -->
                                </div>
                                <!-- /.modal-dialog -->
                            </div>
                            <?php echo form_close(); ?>
                            <!-- /.modal -->   
        			<script language="javascript">
					function isNumberKey(evt)
       				{
          				var charCode = (evt.which) ? evt.which : event.keyCode;
         				 if (charCode != 46 && charCode > 31 
            				&& (charCode < 48 || charCode > 57))
             				return false;

          				return true;
       				}
	   
                    function addItem(){
						
						if(document.getElementById("particular_name").value == ""){
							alert("Please select Paricular Category");
							return false;
						}else if(document.getElementById("bill_name").value == ""){
							alert("Please select Paricular Item");
							return false;
						}else if(document.getElementById("qty").value == ""){
							alert("Please enter a valid Qty");
							return false;
						}else if(document.getElementById("rate").value == ""){
							alert("Please enter a valid Rate");
							return false;
						}else{
							
						
						
						var tbl = document.getElementById('myTable').getElementsByTagName('tr');
						var lastRow = tbl.length;	
						
						var category,particular,qty,rate,note,amount;
						category = document.getElementById("particular_name").value;
						particular = document.getElementById("bill_name").value;
						qty = document.getElementById("qty").value;
						rate = document.getElementById("rate").value;
						note = document.getElementById("note").value;
						
						amount = eval(qty) * eval(rate);
						amount = amount.toFixed(2); 
			
						
						var a=document.getElementById('myTable').insertRow(-1);
						var b=a.insertCell(0);
						var c=a.insertCell(1);
						var d=a.insertCell(2);
						var e=a.insertCell(3);
						var f=a.insertCell(4);
						var g=a.insertCell(5);
						var h=a.insertCell(6);
						
						b.innerHTML = "<input type=\"text\" size = \"7\" style=\"width:98%; background-color:#F9F9f9; border:1px solid #ccc; text-align:right\" name=\"id" + lastRow + "\" id=\"id" + lastRow + "\" value=\""+ lastRow + ". \" readonly=\"true\">";
						c.innerHTML = "<input type=\"text\" size = \"7\" style=\"width:98%; background-color:#F9F9f9; border:1px solid #ccc;\" name=\"bill_name" + lastRow + "\" id=\"bill_name" + lastRow + "\" value=\""+ particular + "\" readonly=\"true\">";
						d.innerHTML = "<input type=\"text\" size = \"7\" style=\"width:98%; text-align:right\" name=\"qty" + lastRow + "\" id=\"qty" + lastRow + "\" class=\"" + lastRow + "\" value=\""+ qty + "\" onBlur=\"return validate_input(this.className,'qty')\" onkeyup=\"validate_gross(this.className,'qty')\" onkeypress=\"return isNumberKey(event)\" >";
						e.innerHTML = "<input type=\"text\" size = \"7\" style=\"width:98%; text-align:right\" name=\"rate" + lastRow + "\" id=\"rate" + lastRow + "\" class=\"" + lastRow + "\" value=\""+ rate + "\" onBlur=\"return validate_input(this.className,'rate')\" onkeyup=\"validate_gross(this.className,'rate')\" onkeypress=\"return isNumberKey(event)\">";
						f.innerHTML = "<input type=\"text\" size = \"7\" style=\"width:98%; background-color:#F9F9f9; border:1px solid #ccc; text-align:right\" name=\"amount" + lastRow + "\" id=\"amount" + lastRow + "\" value=\""+ amount + "\" readonly=\"true\">";
						g.innerHTML = "<input type=\"text\" size = \"7\" style=\"width:98%;\" name=\"note" + lastRow + "\" id=\"note" + lastRow + "\" value=\""+ note + "\">";
						h.innerHTML = "<img src=\"<?php echo base_url()?>public/img/b_drop.png\" onclick=\"deleteRow(this)\" style=\"cursor:pointer;\">";
						
						document.getElementById("hdnrowcnt").value = lastRow;
						
						getGross();
						
						$('#myModal').modal('hide');
						return true;	
					
						}
					}
					
					function deleteRow(r){
						var tbl = document.getElementById('myTable').getElementsByTagName('tr');
						var lastRow = tbl.length;	
						
						var i=r.parentNode.parentNode.rowIndex;
						if (lastRow > 2) {
							document.getElementById('myTable').deleteRow(i);
 							document.getElementById('hdnrowcnt').value = lastRow - 2;
 							var lastRow = tbl.length;
							var z;
							for (z=i+1; z<=lastRow; z++){
								
								var id = document.getElementById('id' + z);
								var bill_name = document.getElementById('bill_name' + z);
								var qty = document.getElementById('qty' + z);
								var rate = document.getElementById('rate' + z);
								var amount = document.getElementById('amount' + z);
								var note = document.getElementById('note' + z);
								
								var x = z-1;
								
								id.value = x;
								id.id = "id" + x;
								id.name = "id" + x;	
								
								bill_name.id = "bill_name" + x;
								bill_name.name = "bill_name" + x;	
								
								qty.id = "qty" + x;
								qty.name = "qty" + x;	
								qty.className = x;
								
								rate.id = "rate" + x;
								rate.name = "rate" + x;	
								rate.className = x;
								
								amount.id = "amount" + x;
								amount.name = "amount" + x;	
								
								note.id = "note" + x;
								note.name = "note" + x;	
								
								//alert(bill_name.name + " - " + rate.value);
							}
							getGross();
						}else{
 							alert("Minimum of one row per transaction.");
 						}
					}
					
					function getGross()
					{
						var len;
						var nGross = 0;
						len = document.getElementById("hdnrowcnt").value;
							for (i=1; i<=len; i++) {
								nGross += parseFloat(document.getElementById("amount" + i).value-0);
							}
						nGross = nGross.toFixed(2);
						document.getElementById("nGross").value = nGross;
						var totalAmountEl = document.getElementById("total_amount");
						if (totalAmountEl) {
							totalAmountEl.value = nGross;
						}
						updateNhisSplit(parseFloat(nGross));
					}

					function updateNhisSplit(gross) {
						var payerEl = document.getElementById('payer_type');
						if (!payerEl || payerEl.value !== 'NHIS') return;
						var pct = <?php echo (float)(isset($nhis_subsidy_pct) ? $nhis_subsidy_pct : 100); ?>;
						if (pct > 100) pct = 100;
						if (pct < 0) pct = 0;
						var covered = Math.round(gross * (pct / 100.0) * 100) / 100;
						var payable = Math.round((gross - covered) * 100) / 100;
						var covEl = document.getElementById('nhis_covered_display');
						var payEl = document.getElementById('patient_pays_display');
						if (covEl) covEl.value = covered.toFixed(2);
						if (payEl) payEl.value = payable.toFixed(2);
					}
					
					function validate_gross(id,nName){
						var qty,rate,amount;
						qty = document.getElementById("qty"+id).value;	
						rate = document.getElementById("rate"+id).value;
						
						amount = eval(qty) * eval(rate);
						amount = amount.toFixed(2); 
						
						document.getElementById("amount"+id).value = amount;
						
						getGross();			
					}
					
					function validate_input(id,name){
						//alert(document.getElementById(name+""+id).value);
						if(document.getElementById(name+""+id).value == "" || eval(document.getElementById(name+""+id).value) <= 0){
							alert("Please enter a valid "+name+".");
							document.getElementById(name+""+id).value = "0";
							validate_gross(id,name)
							getGross();	
							return false;		
						}else{
							validate_gross(id,name)
							getGross();	
						}
					}
					
					function validate_form(){
						
						if(document.getElementById("hdnrowcnt").value == "0"){
							alert('Minimum of one row per transaction.');
							return false;
						}else if(document.getElementById("paymentType").value == ""){
							alert('Please enter Mode of Payment in Header Details Tab');
							return false;
						}else{
							var len;
							len = document.getElementById("hdnrowcnt").value;	
							for (i=1; i<=len; i++) {
							if(eval(document.getElementById("amount"+i).value) <= 0){
								alert("Transaction cannot be saved. There are still some items without amount.");
								return false;
							}else{
								if(confirm('Are you sure you want to save?')){
									return true;
								}else{
									return false;	
								}
							}
						}
						}
						
						
					}
					
					function stopEnterKey(evt) {
        				var evt = (evt) ? evt : ((event) ? event : null);
        				var node = (evt.target) ? evt.target : ((evt.srcElement) ? evt.srcElement : null);
        				if ((evt.keyCode == 13) && (node.type == "text")) { return false; }
    				}
    				document.onkeypress = stopEnterKey;
					 </script>
                    
        
        
    </body>
</html>