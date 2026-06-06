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
                    <h1>OPD Registration</h1>
                   <ol class="breadcrumb">
                        <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                        <li><a href="#">Patient Management</a></li>
                        <li><a href="<?php echo base_url()?>app/opd/index">OPD</a></li>
                        <li class="active">OPD Registration</li>
                    </ol>
                </section>

                <!-- Main content -->
                <section class="content">

                				<?php $fm = $this->session->flashdata('message'); if ($fm): echo $fm; endif; ?>
				<?php $abInfo = $this->session->flashdata('auto_billing_info'); if ($abInfo): echo $abInfo; endif; ?>
				<?php $abWarn = $this->session->flashdata('auto_billing_warning'); if ($abWarn): echo $abWarn; endif; ?>

				<?php
				$vf = isset($visit_fee_preview) ? $visit_fee_preview : null;
				if (is_array($vf) && !empty($vf['ok'])):
					$reg = isset($vf['registration']) ? $vf['registration'] : null;
					$con = isset($vf['consultation']) ? $vf['consultation'] : null;
				?>
				<div class="row">
					<div class="col-md-12">
						<div class="callout callout-info" style="margin-bottom:10px;">
							<h4 style="margin-top:0;"><i class="fa fa-money"></i> Visit Fee Preview</h4>
							<p style="margin:0;">
								<strong>Payer:</strong> <?php echo htmlspecialchars(isset($vf['payer_type']) ? (string)$vf['payer_type'] : ''); ?>
								&nbsp;|&nbsp;
								<strong>Registration:</strong>
								<?php if (is_array($reg) && isset($reg['decision']) && $reg['decision'] === 'APPLY'): ?>
									<span class="label label-primary">Apply</span>
									<?php if (!empty($reg['amount'])): ?> <span class="text-muted">(GHS <?php echo number_format((float)$reg['amount'], 2); ?>)</span><?php endif; ?>
								<?php elseif (is_array($reg) && isset($reg['decision']) && $reg['decision'] === 'ERROR'): ?>
									<span class="label label-danger">Error</span>
								<?php else: ?>
									<span class="label label-default">Skip</span>
								<?php endif; ?>
								&nbsp;|&nbsp;
								<strong>Consultation:</strong>
								<?php if (is_array($con) && isset($con['decision']) && $con['decision'] === 'WAIVE'): ?>
									<span class="label label-success">Waived</span>
								<?php elseif (is_array($con) && isset($con['decision']) && $con['decision'] === 'APPLY'): ?>
									<span class="label label-primary">Apply</span>
									<?php if (!empty($con['amount'])): ?> <span class="text-muted">(GHS <?php echo number_format((float)$con['amount'], 2); ?>)</span><?php endif; ?>
								<?php elseif (is_array($con) && isset($con['decision']) && $con['decision'] === 'ERROR'): ?>
									<span class="label label-danger">Error</span>
								<?php else: ?>
									<span class="label label-default">Skip</span>
								<?php endif; ?>
							</p>
						</div>
					</div>
				</div>
				<?php endif; ?>

				 <div class="row">
					<div class="col-md-12">
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
										<img src="<?php echo base_url();?>public/patient_picture/<?php echo $picture;?>" class="img-rounded" width="110" height="110">
										</td>
										<td width="85%">
											<table width="100%">
											<tr>
												<td><u>Patient No.</u></td>
												<td width="13%"><u>Age</u></td>
												<td width="63%"><u>Address</u></td>
											</tr>
											<tr>
												<td><?php echo $patientInfo->patient_no?></td>
												<td><?php echo $patientInfo->age?></td>
												<td><?php echo $patientInfo->address?></td>
											</tr>
											<tr>
												<td width="24%"><u>Patient Name</u></td>
												<td><u>Gender</u></td>
												<td><u>Civil Status</u></td>
											</tr>
											<tr>
												<td width="24%"><?php echo $patientInfo->name?></td>
												<td><?php echo $patientInfo->gender?></td>
												<td><?php echo $patientInfo->civil_status?></td>
											</tr>
											</table>
										</td>
									</tr>
								</table>
							</div>
							<div class="box-footer clearfix">
								
							</div>
						</div>
					</div>
				 </div>

				 <?php
				$clearanceBlocked = $this->session->flashdata('clearance_blocked');
				$clearanceData = $clearanceBlocked ? json_decode($clearanceBlocked, true) : null;
				$outstandingBlocked = $this->session->flashdata('outstanding_blocked');
				$outstandingData = $outstandingBlocked ? json_decode($outstandingBlocked, true) : null;
				$isAdmin = has_role('admin');
				if ($outstandingData && isset($outstandingData['balance']) && (float)$outstandingData['balance'] > 0.009):
				?>
                 <div class="row">
                    <div class="col-md-12">
                        <div class="callout callout-danger">
                            <h4><i class="fa fa-ban"></i> Registration Blocked — Outstanding Balance</h4>
                            <p>Patient has an outstanding balance of <strong>GHS <?php echo number_format((float)$outstandingData['balance'], 2); ?></strong> from previous invoice(s). Please settle at the cashier before creating a new visit.</p>
                            <?php if (!empty($outstandingData['invoices']) && is_array($outstandingData['invoices'])): ?>
                            <ul>
                                <?php foreach ($outstandingData['invoices'] as $inv): ?>
                                    <li><strong><?php echo htmlspecialchars((string)(isset($inv['invoice_no']) ? $inv['invoice_no'] : '')); ?></strong> — Balance: GHS <?php echo number_format((float)(isset($inv['balance_due']) ? $inv['balance_due'] : 0), 2); ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                 </div>
                 <?php endif; ?>
				 <?php if ($clearanceData && !empty($clearanceData['blocking_iop'])): ?>
                 <div class="row">
                    <div class="col-md-12">
                        <div class="callout callout-danger">
                            <h4><i class="fa fa-ban"></i> Registration Blocked — Uncleared Previous Visit</h4>
                            <p>Patient has an active uncleared visit: <strong><?php echo htmlspecialchars($clearanceData['blocking_iop']); ?></strong>. Resolve the following pending items before creating a new visit:</p>
                            <ul>
                            <?php foreach ($clearanceData['pending_items'] as $pi): ?>
                                <li><i class="fa <?php echo htmlspecialchars($pi['icon']); ?>"></i> <strong><?php echo htmlspecialchars($pi['label']); ?></strong> (<?php echo (int)$pi['count']; ?>)</li>
                            <?php endforeach; ?>
                            </ul>
                            <?php if ($isAdmin): ?>
                            <hr>
                            <p class="text-warning"><strong><i class="fa fa-exclamation-triangle"></i> Admin Override</strong> — You may override this restriction. This action will be logged.</p>
                            <div id="overridePanel">
                                <div class="form-group">
                                    <label>Override Reason <span class="text-danger">*</span></label>
                                    <input type="text" id="overrideReasonInput" class="form-control input-sm" placeholder="Required: Enter reason for override..." style="max-width:450px;">
                                </div>
                                <button type="button" id="btnShowOverrideForm" class="btn btn-warning btn-sm"><i class="fa fa-unlock"></i> Proceed with Override</button>
                            </div>
                            <?php else: ?>
                            <p class="text-muted">Contact an administrator to override this restriction.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                 </div>
                 <?php endif; ?>

                 <form method="post" id="opdRegForm" action="<?php echo base_url();?>app/opd/save_opd" onSubmit="return confirm('Are you sure you want to save?');">
                 <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                 <input type="hidden" name="patient_no" value="<?php echo $patientInfo->patient_no?>">
                 <input type="hidden" name="override_reason" id="override_reason" value="">
                 <div class="row">
                 	
                     <div class="col-md-12">
                    	 <div class="box">
                         	 <div class="box-header">
                             	<div class="box-footer clearfix">
                            	
                                            <a href="<?php echo base_url();?>app/patient" class="btn btn-default">Cancel</a>
                                            <button class="btn btn-primary" name="btnSubmit" id="btnSubmit" type="submit"><i class="fa fa-save"></i> Save</button>
                                 
                            	</div>
                             </div>
                        	<div class="box-body table-responsive">
                            		
                                    
                            						<?php
													$userID = $lastOPDNo->opdNo;
													$userID2 = $lastOPDNo->opdNo;
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
                                 <?php echo validation_errors();?>                  
                                <div class="nav-tabs-custom">
                                	<ul class="nav nav-tabs">
                                		<li class="active"><a href="#tab_1" data-toggle="tab">General Information</a></li>
                                    	<li><a href="#tab_3" data-toggle="tab">Vital Parameters</a></li>
                                        <li><a href="#tab_2" data-toggle="tab">Patient History</a></li>
                                        
                                	</ul>
                                    <input type="hidden" name="userID2" value="<?php echo $userID2?>">
                                    <div class="tab-content">
                                    	<div class="tab-pane active" id="tab_1">
                                        	<table width="100%" cellpadding="3" cellspacing="3">
                                <tr>
                                	<td width="21%">OPD No.</td>
                                    <td width="79%"><input class="form-control input-sm" name="opdNo" id="opdNo" type="text" style="width: 100px;" required readonly value="<?php echo "OP-".$userID;?>"></td>
                                </tr>
                                <tr>
                                	<td>Referal Doctor</td>
                                    <td>
                                    						<select name="refdoctor" id="refdoctor" class="form-control input-sm" style="width: 200px;">
                                                            	<option value="">- Referal Doctor -</option>
                                                            	<?php 
																foreach($doctorList as $doctorList){
																if(isset($_POST['refdoctor']) && $_POST['refdoctor'] == $doctorList->user_id){
																	$selected = "selected='selected'";
																}else{
																	$selected = "";
																}
																?>
                                                            	<option value="<?php echo $doctorList->user_id;?>" <?php echo $selected;?>><?php echo $doctorList->name;?></option>
                                                                <?php }?>
                                                            </select>
                                    </td>
                                </tr>
                                <tr>
                                	<td>Department <span class="text-danger" title="Required">*</span></td>
                                    <td>
                          						<select name="department" id="department" class="form-control input-sm select2" style="width: 300px;" required>
                                      	<option value="">-- Select Department --</option>
                                      	<?php 
        										foreach($departmentList as $departmentList){
        										if(isset($_POST['department']) && $_POST['department'] == $departmentList->department_id){
        											$selected = "selected='selected'";
        										}else{
        											$selected = "";
        										}
        										?>
                                      	<option value="<?php echo $departmentList->department_id;?>" <?php echo $selected;?>><?php echo $departmentList->dept_name;?></option>
                                          <?php }?>
                                      </select>
                                      <span id="dept_error" class="text-danger" style="display:none;font-size:12px;"><i class="fa fa-warning"></i> Department is required</span>
                                    </td>
                                </tr>
                                <tr>
                                	<td>Consultant Doctor</td>
                                    <td>
										<select name="doctor" id="doctor" class="form-control input-sm" style="width: 200px;">
											<option value="">- Auto Assign -</option>
											<?php 
	        											foreach($doctorList2 as $doctorList2){
	        											if(isset($_POST['doctor']) && $_POST['doctor'] == $doctorList2->user_id){
	        												$selected = "selected='selected'";
	        											}else{
	        												$selected = "";
	        											}
	        											?>
												<option value="<?php echo $doctorList2->user_id;?>" <?php echo $selected;?>><?php echo $doctorList2->name;?></option>
                                          <?php }?>
                                      </select>
                                    </td>
                                </tr>
								<tr>
									<td>Insurance Cover</td>
									<td>
										<?php
										$currentInsComp = isset($patientInfo->Insurance_comp) ? trim((string)$patientInfo->Insurance_comp) : '';
										$patientNhisNumber = isset($patientInfo->nhis_number) ? trim((string)$patientInfo->nhis_number) : '';
										?>
										<select name="insurance_cover_id" id="insurance_cover_id" class="form-control input-sm" style="width: 250px;" onchange="onInsuranceChange()">
											<option value="" data-type="SELF_PAY">- Self Pay / None -</option>
											<?php if (isset($insurance_list) && is_array($insurance_list)): foreach ($insurance_list as $ins):
												$sel = '';
												if ($currentInsComp !== '' && strtolower($currentInsComp) === strtolower($ins->company_name)) {
													$sel = "selected='selected'";
												}
												$typeLabel = isset($ins->insurance_type) ? $ins->insurance_type : '';
												$isNhis = (stripos($ins->company_name, 'nhis') !== false || strtoupper($typeLabel) === 'NHIS');
											?>
											<option value="<?php echo $ins->in_com_id; ?>" data-type="<?php echo $typeLabel; ?>" data-billing="<?php echo isset($ins->billing_type) ? $ins->billing_type : ''; ?>" data-is-nhis="<?php echo $isNhis ? '1' : '0'; ?>" <?php echo $sel; ?>><?php echo $ins->company_name; ?> (<?php echo $typeLabel; ?>)</option>
											<?php endforeach; endif; ?>
										</select>
										<input type="hidden" name="insurance_billing_type" id="insurance_billing_type" value="">
										<input type="hidden" id="patient_nhis_number" value="<?php echo htmlspecialchars($patientNhisNumber, ENT_QUOTES, 'UTF-8'); ?>">
										<input type="hidden" id="patient_no_hidden" value="<?php echo htmlspecialchars($patientInfo->patient_no, ENT_QUOTES, 'UTF-8'); ?>">
									</td>
								</tr>
								<tr id="nhis_verification_row" style="display:none;">
									<td>NHIS Verification</td>
									<td>
										<div id="nhis_verify_status">
											<span class="label label-default"><i class="fa fa-clock-o"></i> Pending verification...</span>
										</div>
									</td>
								</tr>
								<tr>
									<td>Insurance Card Status</td>
									<td>
										<?php
										$insSt = isset($patientInfo->insurance_card_status) ? strtoupper(trim((string)$patientInfo->insurance_card_status)) : 'ACTIVE';
										if ($insSt !== 'ACTIVE' && $insSt !== 'INACTIVE' && $insSt !== 'N/A') {
											$insSt = 'ACTIVE';
										}
										?>
										<select name="insurance_card_status" id="insurance_card_status" class="form-control input-sm" style="width: 200px;">
											<option value="ACTIVE" <?php echo ($insSt === 'ACTIVE') ? "selected='selected'" : ""; ?>>Active</option>
											<option value="INACTIVE" <?php echo ($insSt === 'INACTIVE') ? "selected='selected'" : ""; ?>>Inactive</option>
											<option value="N/A" <?php echo ($insSt === 'N/A') ? "selected='selected'" : ""; ?>>N/A (Self Pay)</option>
										</select>
									</td>
								</tr>
                                <input type="hidden" name="diagnosis">
                                <input type="hidden" name="complaints">
                                <!--<tr>
                                 	<td valign="top">Provisional Diagnosis</td>
                                 	<td><textarea name="diagnosis" id="diagnosis" class="form-control input-sm" style="width: 60%;" rows="3"></textarea></td>
                                </tr>
                                <tr>
                                	<td valign="top">Complaints</td>
                                	<td><textarea name="complaints" id="complaints" class="form-control input-sm" style="width: 60%;" rows="3"></textarea></td>
                                </tr>-->
                                
                                </table>
                                        </div>
                                        <div class="tab-pane" id="tab_2">
                                        <table width="100%" cellpadding="3" cellspacing="3">
                                        <tr>
                                	<td width="21%" valign="top">Allergies</td>
                                	<td width="79%"><textarea readonly disabled name="allergies" id="allergies" class="form-control input-sm" style="width: 60%;" rows="3"><?php echo ($patientHistory) ? $patientHistory->allergies : "";?></textarea></td>
                                </tr>
                                <tr>
                                	<td valign="top">Warnings</td>
                                	<td><textarea readonly disabled name="warnings" id="warnings" class="form-control input-sm" style="width: 60%;" rows="3"><?php echo ($patientHistory) ? $patientHistory->warnings : "";?></textarea></td>
                                </tr>
                                <tr>
                                	<td valign="top">Social History</td>
                                	<td><textarea readonly disabled name="social_history" id="social_history" class="form-control input-sm" style="width: 60%;" rows="3"><?php echo ($patientHistory) ? $patientHistory->social_history : "";?></textarea></td>
                                </tr>
                                <tr>
                                	<td valign="top">Family History</td>
                                	<td><textarea readonly disabled name="family_history" id="family_history" class="form-control input-sm" style="width: 60%;" rows="3"><?php echo ($patientHistory) ? $patientHistory->family_history : "";?></textarea></td>
                                </tr>
                                <tr>
                                	<td valign="top">Personal History</td>
                                	<td><textarea readonly disabled name="personal_history" id="personal_history" class="form-control input-sm" style="width: 60%;" rows="3"><?php echo ($patientHistory) ? $patientHistory->personal_history : "";?></textarea></td>
                                </tr>
                                <tr>
                                	<td valign="top">Past Medical History</td>
                                	<td><textarea readonly disabled name="past_medical_history" id="past_medical_history" class="form-control input-sm" style="width: 60%;" rows="3"><?php echo ($patientHistory) ? $patientHistory->past_medical_history : "";?></textarea></td>
                                </tr>
                                        </table>
                                        </div>
                                        <div class="tab-pane" id="tab_3">
                                        <table width="100%" cellpadding="3" cellspacing="3">
                                        <tr>
                                        	<td width="12%">Pulse rate</td>
                                            <Td width="28%"><input type="text" name="pulse_rate" id="pulse_rate" style="width: 100px;">&nbsp;&nbsp;/min</Td>
                                        	<td width="12%">BP</td>
                                            <Td width="48%"><input type="text" name="bp" id="bp"  style="width: 100px;">&nbsp;&nbsp;mm of Hg</Td>
                                        </tr>
                                        <tr>
                                        	<td>Temperature</td>
                                            <Td><input type="text" name="temperature" id="temperature" style="width: 100px;">&nbsp;&nbsp;C</Td>
                                        	<td>Respiration</td>
                                            <Td><input type="text" name="respiration" id="respiration"  style="width: 100px;">&nbsp;&nbsp;/min</Td>
                                        </tr>
                                        <tr>
                                        	<td>Height</td>
                                            <Td><input type="text" name="height" id="height"  style="width: 100px;">&nbsp;&nbsp;Cm</Td>
                                        	<td>Weight</td>
                                            <Td><input type="text" name="weight" id="weight"  style="width: 100px;">&nbsp;&nbsp;Kg</Td>
                                        </tr>
                                        <tr>
                                        	<td>SPO2</td>
                                            <Td><input type="text" name="spo2" id="spo2" style="width: 100px;">&nbsp;&nbsp;%</Td>
                                        	<td></td>
                                            <Td></Td>
                                        </tr>
                                        
                                        </table>
                                        </div>
                                    </div>
                                </div>
                            	
                                 <input type="hidden" name="userID2" value="<?php echo $userID2;?>">
                                 
                                     
                                
                           </div>
                            <div class="box-footer clearfix">
                                	
                            </div>
                        </div>
                    </div>
                 </div>
                 </form>
                 
                </section><!-- /.content -->
            </aside><!-- /.right-side -->
        </div><!-- ./wrapper -->
  
        
         <script src="<?php echo base_url();?>public/js/jquery.min.js"></script>
         <script src="<?php echo base_url();?>public/js/bootstrap.min.js" type="text/javascript"></script>     
        <script src="<?php echo base_url();?>public/js/AdminLTE/app.js" type="text/javascript"></script>
         <!-- BDAY -->
        <script src="<?php echo base_url();?>public/datepicker/js/bootstrap-datepicker.js"></script>
        <script type="text/javascript">
            var csrfName = '<?php echo $this->security->get_csrf_token_name(); ?>';
            var csrfHash = '<?php echo $this->security->get_csrf_hash(); ?>';
            // When the document is ready
            $(document).ready(function () {

                // Select2 on department — searchable, required
                if ($.fn.select2) {
                    $('#department').select2({
                        placeholder: '-- Select Department --',
                        allowClear: false,
                        width: '300px'
                    });
                }

                // Client-side department validation before submit
                $('#opdRegForm').on('submit', function (e) {
                    var dept = $('#department').val();
                    if (!dept || dept === '') {
                        e.preventDefault();
                        $('#dept_error').show();
                        $('#department').closest('td').find('.select2-selection').css('border-color', '#dd4b39');
                        $('html, body').animate({scrollTop: $('#department').closest('tr').offset().top - 100}, 400);
                        return false;
                    }
                    $('#dept_error').hide();
                });

                $('#department').on('change', function () {
                    if ($(this).val()) {
                        $('#dept_error').hide();
                        $(this).closest('td').find('.select2-selection').css('border-color', '');
                    }
                });
                
                $('#cFrom').datepicker({
                    //format: "dd/mm/yyyy"
					format: "yyyy-mm-dd"
                });  
				
				$('#cTo').datepicker({
                    //format: "dd/mm/yyyy"
					format: "yyyy-mm-dd"
                });  

                // Auto-set billing type on page load if insurance is pre-selected
                onInsuranceChange();

                // Admin override handler
                $('#btnShowOverrideForm').on('click', function () {
                    var reason = $.trim($('#overrideReasonInput').val());
                    if (reason === '') {
                        alert('Please enter an override reason before proceeding.');
                        return;
                    }
                    if (!confirm('Override registration restriction? This action will be permanently logged.\n\nReason: ' + reason)) {
                        return;
                    }
                    $('#override_reason').val(reason);
                    $('#opdRegForm').off('submit').submit();
                });
            });

            function onInsuranceChange(){
                var sel = document.getElementById('insurance_cover_id');
                var hidden = document.getElementById('insurance_billing_type');
                var cardStatusSel = document.getElementById('insurance_card_status');
                var nhisVerifyRow = document.getElementById('nhis_verification_row');
                var nhisVerifyStatus = document.getElementById('nhis_verify_status');
                
                if (!sel || !hidden) return;
                var opt = sel.options[sel.selectedIndex];
                var insType = opt ? (opt.getAttribute('data-type') || '') : '';
                var isNhis = opt ? (opt.getAttribute('data-is-nhis') === '1') : false;
                var insValue = opt ? opt.value : '';
                
                // Set billing type
                if (opt && insValue !== '') {
                    hidden.value = opt.getAttribute('data-billing') || '';
                } else {
                    hidden.value = '';
                }
                
                // Handle Self Pay / None selection
                if (insValue === '' || insType === 'SELF_PAY') {
                    // Self Pay selected - disable card status and set to N/A
                    if (cardStatusSel) {
                        cardStatusSel.value = 'N/A';
                        cardStatusSel.disabled = true;
                        cardStatusSel.style.backgroundColor = '#f5f5f5';
                    }
                    // Hide NHIS verification row
                    if (nhisVerifyRow) {
                        nhisVerifyRow.style.display = 'none';
                    }
                } else {
                    // Insurance selected - enable card status
                    if (cardStatusSel) {
                        cardStatusSel.disabled = false;
                        cardStatusSel.style.backgroundColor = '';
                        // If currently N/A, switch to ACTIVE
                        if (cardStatusSel.value === 'N/A') {
                            cardStatusSel.value = 'ACTIVE';
                        }
                    }
                    
                    // Check if NHIS is selected
                    if (isNhis) {
                        // Show NHIS verification row and auto-verify
                        if (nhisVerifyRow) {
                            nhisVerifyRow.style.display = '';
                        }
                        autoVerifyNhis();
                    } else {
                        // Hide NHIS verification row for non-NHIS insurance
                        if (nhisVerifyRow) {
                            nhisVerifyRow.style.display = 'none';
                        }
                    }
                }
            }
            
            function autoVerifyNhis() {
                var nhisNumber = $('#patient_nhis_number').val();
                var patientNo = $('#patient_no_hidden').val();
                var $status = $('#nhis_verify_status');
                var $cardStatus = $('#insurance_card_status');
                
                if (!nhisNumber || nhisNumber.trim() === '') {
                    $status.html(
                        '<span class="label label-warning" style="font-size:12px;padding:5px 10px;">' +
                        '<i class="fa fa-exclamation-triangle"></i> No NHIS number on file. Please update patient record first.' +
                        '</span>'
                    );
                    // Set card status to INACTIVE since no NHIS number
                    $cardStatus.val('INACTIVE');
                    return;
                }
                
                $status.html(
                    '<span class="label label-info" style="font-size:12px;padding:5px 10px;">' +
                    '<i class="fa fa-spinner fa-spin"></i> Verifying NHIS card: ' + nhisNumber + '...' +
                    '</span>'
                );
                
                var verifyData = {nhis_number: nhisNumber, patient_no: patientNo};
                verifyData[csrfName] = csrfHash;
                $.ajax({
                    url: '<?php echo base_url(); ?>app/patient/nhis_verify_ajax',
                    type: 'POST',
                    data: verifyData,
                    dataType: 'json',
                    timeout: 15000,
                    success: function(r) {
                        if (r && r.success) {
                            var exp = r.expiry_date || '';
                            var scheme = r.scheme || r.scheme_name || '';
                            var name = r.name || r.member_name || '';
                            
                            $status.html(
                                '<span class="label label-success" style="font-size:12px;padding:5px 10px;">' +
                                '<i class="fa fa-check-circle"></i> ACTIVE' +
                                (exp ? ' | Expires: ' + exp : '') +
                                (scheme ? ' | ' + scheme : '') +
                                '</span>' +
                                (name ? '<br><small class="text-muted">Member: ' + name + '</small>' : '')
                            );
                            // Auto-set card status to ACTIVE
                            $cardStatus.val('ACTIVE');
                        } else {
                            var msg = r.message || 'Verification failed';
                            $status.html(
                                '<span class="label label-danger" style="font-size:12px;padding:5px 10px;">' +
                                '<i class="fa fa-times-circle"></i> ' + msg +
                                '</span>'
                            );
                            // Auto-set card status to INACTIVE
                            $cardStatus.val('INACTIVE');
                        }
                    },
                    error: function(xhr, status, error) {
                        if (status === 'timeout') {
                            $status.html(
                                '<span class="label label-warning" style="font-size:12px;padding:5px 10px;">' +
                                '<i class="fa fa-clock-o"></i> Verification timed out. NHIS server may be slow.' +
                                '</span> <button type="button" class="btn btn-xs btn-info" onclick="autoVerifyNhis()"><i class="fa fa-refresh"></i> Retry</button>'
                            );
                        } else {
                            $status.html(
                                '<span class="label label-danger" style="font-size:12px;padding:5px 10px;">' +
                                '<i class="fa fa-exclamation-circle"></i> Server error. Please try again.' +
                                '</span> <button type="button" class="btn btn-xs btn-info" onclick="autoVerifyNhis()"><i class="fa fa-refresh"></i> Retry</button>'
                            );
                        }
                        // Keep current card status on error
                    }
                });
            }
        </script>
        <!-- END BDAY -->
        
        
    </body>
</html>