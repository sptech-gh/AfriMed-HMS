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
    <body class="skin-blue" >
        <!-- header logo: style can be found in header.less -->
        <?php require_once(APPPATH.'views/include/header.php');?>
        
        <div class="wrapper row-offcanvas row-offcanvas-left">
            
            <?php require_once(APPPATH.'views/include/sidebar.php');?>
 <script language="javascript">
                 function validate(){
							action = '';
							if (document.getElementById('submit_action')) {
								action = document.getElementById('submit_action').value;
							}
							if(action === 'draft'){
								return true;
							}
							// Check required Result field
							var resultField = document.getElementById('result');
							if (resultField && resultField.value.trim() === '') {
								alert('Result field is required. Please enter the test result.');
								resultField.focus();
								return false;
							}
							if(confirm('Are you sure you want to save?')){
								return true;
							}else{
								return false;
							}
					 }
                 </script>
            <!-- Right side column. Contains the navbar and content of the page -->
            <aside class="right-side">                
                <!-- Content Header (Page header) -->
                <section class="content-header">
                    <h1><?php echo urldecode($lab_request_name);?></h1>
                    <ol class="breadcrumb">
                        <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                        <li class="active"><a href="#">Lab Management</a></li>
                        <!-- <li class="active">Modify Patient</li> -->
                    </ol>
                </section>

                <!-- Main content -->
                <section class="content">
 　　　　　　　 
 　　　　　　　 <div class="row">
                 	<div class="col-md-12">
					<?php $moduleBase = isset($module_base) && $module_base ? (string)$module_base : 'laboratory'; ?>
					<?php $saveAction = base_url().'app/'.$moduleBase.'/save_result/'.$lab.'/'.$lab_patient; ?>
					<form role="form" method="post" action="<?php echo $saveAction; ?>" onSubmit="return validate()">    
                        <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                        <input type="text" name="io_lab_id" hidden value="<?php echo $lab;?>">
                        <input type="text" name="iop_id" hidden value="<?php echo $lab_patient;?>">
                        <?php if ($moduleBase === 'sonography') { ?><input type="hidden" id="submit_action" name="submit_action" value="publish"><?php } ?>

                         <?php
                            $isReadOnly = isset($is_read_only) && $is_read_only;
                            $paymentBlocked = isset($payment_blocked) && $payment_blocked;
                            $wf = isset($workflow) ? $workflow : null;
                            $wfStatus = $wf && isset($wf->status) ? strtoupper(trim((string)$wf->status)) : '';
                            $wfRequestedAt = $wf && isset($wf->requested_at) ? (string)$wf->requested_at : '';
                            $wfReportedAt = $wf && isset($wf->reported_at) ? (string)$wf->reported_at : '';
                            $labRow = isset($lab_row) ? $lab_row : null;
                            $findingsVal = $labRow && isset($labRow->findings) ? (string)$labRow->findings : '';
                            $resultVal = $labRow && isset($labRow->result) ? (string)$labRow->result : '';
                            if (strtolower(trim($findingsVal)) === 'uploaded') { $findingsVal = ''; }
                            if (strtolower(trim($resultVal)) === 'uploaded') { $resultVal = ''; }
							$draftRow = isset($draft) ? $draft : null;
							$draftFindings = $draftRow && isset($draftRow->findings) ? (string)$draftRow->findings : '';
							$draftResult = $draftRow && isset($draftRow->result) ? (string)$draftRow->result : '';
							if (trim($findingsVal) === '' && trim($draftFindings) !== '') { $findingsVal = $draftFindings; }
							if (trim($resultVal) === '' && trim($draftResult) !== '') { $resultVal = $draftResult; }
                         ?>
                         <?php $flashMsg = $this->session->flashdata('message'); if ($flashMsg) { echo $flashMsg; } ?>
                         <?php if ($paymentBlocked) { 
                             $payInfo = isset($payment_status) ? $payment_status : null;
                             $payLabel = ($payInfo && isset($payInfo['label'])) ? $payInfo['label'] : 'Pending';
                         ?>
                         <div class="alert alert-danger">
                             <i class="fa fa-ban fa-lg"></i> <strong>PAYMENT REQUIRED:</strong> 
                             Lab results cannot be saved until payment is verified. 
                             Payment Status: <strong><?php echo htmlspecialchars($payLabel, ENT_QUOTES, 'UTF-8'); ?></strong>
                             <br><small>Please direct the patient to the cashier/billing desk for payment before processing this test.</small>
                         </div>
                         <?php } ?>
                         <div class="box">

                         		
                          		 <div class="box-footer clearfix">
	                            
                                            <a href="<?php echo base_url();?>app/<?php echo $moduleBase; ?>/request/<?php echo rawurlencode($lab_patient);?>" class="btn btn-default">Cancel</a>
                                            <?php if (!$isReadOnly && !$paymentBlocked) { ?>
                                            <?php if ($moduleBase === 'sonography') { ?><button class="btn btn-default" name="btnDraft" id="btnDraft" type="submit" onclick="document.getElementById('submit_action').value='draft';"><i class="fa fa-save"></i> Save Draft</button><?php } ?>
                                            <button class="btn btn-primary" name="btnSubmit" id="btnSubmit" type="submit" onclick="if(document.getElementById('submit_action')){document.getElementById('submit_action').value='publish';}"><i class="fa fa-save"></i> Save</button>
                                            <?php } else if (!$isReadOnly && $paymentBlocked) { ?>
                                            <span class="btn btn-danger disabled"><i class="fa fa-ban"></i> Payment Required - Cannot Save</span>
                                            <?php } ?>
                                 
                            </div>

                            	
                        	<div class="box-body table-responsive">
                            	
                                

                                		<div class="nav-tabs-custom">
                                        	<ul class="nav nav-tabs">
                                				<li class="active"><a href="#tab_1" data-toggle="tab">Results (Text)</a></li>
                                    			<li><a href="#tab_structured" data-toggle="tab"><i class="fa fa-th-list"></i> Structured Results</a></li>
                                    			<li><a href="#tab_2" data-toggle="tab">Upload Results</a></li>
                                                <!-- <li><a href="#tab_4" data-toggle="tab">Other Information</a></li>
                                                <li><a href="#tab_3" data-toggle="tab">Profile Picture</a></li>
                                                <li><a href="#tab_5" data-toggle="tab">Emergency Contact Information</a></li> -->
                                			</ul>
                                            <div class="tab-content">
                                            	
                                                
                                                
                                            	<div class="tab-pane active" id="tab_1">
                                                    <div class="table-responsive">
                                                	<table cellpadding="3" cellspacing="3" width="100%">
                                                    <tr>
                                                    	<td colspan="2">Required fields = <font color="#FF0000">*</font></td>
                                                    </tr>
											<tr>
												<td width="12%"><label class="control-label">Status:</label></td>
												<td width="88%">
													<?php
														$label = $wfStatus;
														if ($wfStatus === 'REQUESTED') {
															$label = '<span class="label label-info">Requested</span>';
														} else if ($wfStatus === 'IN_PROGRESS') {
															$label = '<span class="label label-warning">In Progress</span>';
														} else if ($wfStatus === 'CANCELLED') {
															$label = '<span class="label label-danger">Cancelled</span>';
														} else if ($wfStatus === 'REPORTED_TEXT' || $wfStatus === 'REPORTED_PDF' || $wfStatus === 'REPORTED_BOTH') {
															$label = '<span class="label label-success">Reported</span>';
														} else if ($wfStatus !== '') {
															$label = '<span class="label label-default">'.$wfStatus.'</span>';
														}

													echo $label;
													?>
													<?php if ($wfRequestedAt !== '' && $wfRequestedAt !== '0000-00-00 00:00:00') { ?>
														<span style="margin-left:10px;">Requested: <?php echo date('M d, Y H:i', strtotime($wfRequestedAt)); ?></span>
													<?php } ?>
													<?php if ($wfReportedAt !== '' && $wfReportedAt !== '0000-00-00 00:00:00') { ?>
														<span style="margin-left:10px;">Reported: <?php echo date('M d, Y H:i', strtotime($wfReportedAt)); ?></span>
													<?php } ?>
												</td>
											</tr>
											<?php
											$wfCancelReason = $wf && isset($wf->cancel_reason) ? trim((string)$wf->cancel_reason) : '';
											if ($wfStatus === 'CANCELLED') {
											?>
											<tr>
												<td width="12%"><label class="control-label">Cancellation:</label></td>
												<td width="88%"><span class="label label-danger">Cancelled</span><?php if ($wfCancelReason !== '') { ?><div style="margin-top:6px;">Reason: <?php echo htmlspecialchars($wfCancelReason, ENT_QUOTES, 'UTF-8'); ?></div><?php } ?></td>
											</tr>
											<?php } ?>
											<?php
											// Payment status row (lab workflow enhancement)
											$payInfo = isset($payment_status) ? $payment_status : null;
											if ($payInfo) {
												$payLabel = isset($payInfo['label']) ? $payInfo['label'] : 'Unknown';
												$payPaid = isset($payInfo['paid']) ? $payInfo['paid'] : false;
												if ($payPaid) {
													$payBadge = '<span class="label label-success"><i class="fa fa-check"></i> '.$payLabel.'</span>';
												} else if (strpos($payLabel, 'Partial') !== false) {
													$payBadge = '<span class="label label-warning"><i class="fa fa-exclamation-triangle"></i> '.$payLabel.'</span>';
												} else if ($payLabel === 'No Invoice') {
													$payBadge = '<span class="label label-default">'.$payLabel.'</span>';
												} else {
													$payBadge = '<span class="label label-danger"><i class="fa fa-ban"></i> '.$payLabel.'</span>';
												}
											?>
											<tr>
												<td width="12%"><label class="control-label">Payment:</label></td>
												<td width="88%"><?php echo $payBadge; ?></td>
											</tr>
											<?php } ?>
											<?php
								$techId = $wf && isset($wf->technician_id) ? trim((string)$wf->technician_id) : '';
								$verifiedBy = $wf && isset($wf->verified_by) ? trim((string)$wf->verified_by) : '';
								$doctorAckAt = $wf && isset($wf->doctor_acknowledged_at) ? trim((string)$wf->doctor_acknowledged_at) : '';
								$ioLabIdVal  = isset($lab) ? (int)$lab : 0;
								$moduleBase  = isset($module_base) ? (string)$module_base : 'laboratory';
								$isLabUser   = (isset($hasAccesstoLaboratory) && $hasAccesstoLaboratory);
								$isSonographer = (isset($hasAccesstoSonography) && $hasAccesstoSonography);
								$isDoctorViewer = (isset($hasAccesstoDoctor) && $hasAccesstoDoctor);
								$isAdminUser    = (isset($hasAccesstoAdmin) && $hasAccesstoAdmin);
								$canAssignTech  = (!$isReadOnly && ($isLabUser || $isSonographer || $isAdminUser));
								$canSupVerify   = ($isAdminUser || (isset($userInfo) && in_array(strtolower((string)(isset($userInfo->user_role) ? $userInfo->user_role : '')), array('lab_supervisor','pathologist','radiologist','senior_sonographer'))));
								$canDoctorAck   = ($isDoctorViewer || $isAdminUser);
								$reportedArr    = array('REPORTED_TEXT','REPORTED_PDF','REPORTED_BOTH','REPORTED','VERIFIED');
								$isReported     = in_array($wfStatus, $reportedArr);
								$bill = isset($billing_status) ? $billing_status : null;
								$billPending = ($bill && isset($bill['is_pending']) && $bill['is_pending']);
								$billLabel   = ($bill && isset($bill['label'])) ? (string)$bill['label'] : 'Billing Pending';
								if ($isDoctorViewer && $billPending) {
								?>
								<tr>
									<td width="12%"><label class="control-label">Billing:</label></td>
									<td width="88%"><div class="alert alert-warning" style="margin:0;"><i class="fa fa-warning"></i> <?php echo htmlspecialchars($billLabel, ENT_QUOTES, 'UTF-8'); ?>. Doctor can view results even if unpaid.</div></td>
								</tr>
								<?php } ?>
								<tr>
									<td width="12%"><label class="control-label">Technician:</label></td>
									<td width="88%">
										<?php if ($techId !== '') { ?>
											<span class="text-muted" id="techDisplay"><i class="fa fa-user"></i> <?php echo htmlspecialchars($techId, ENT_QUOTES, 'UTF-8'); ?></span>
										<?php } else { ?>
											<span class="text-muted" id="techDisplay"><i class="fa fa-user-times"></i> Not assigned</span>
										<?php } ?>
										<?php if ($canAssignTech) { ?>
										<button type="button" class="btn btn-xs btn-default" id="btnAssignTech" style="margin-left:8px;"><i class="fa fa-pencil"></i> Assign</button>
										<span id="assignTechForm" style="display:none;margin-left:8px;">
											<input type="text" id="techInput" class="form-control input-sm" style="display:inline-block;width:180px;" placeholder="Technician name" value="<?php echo htmlspecialchars($techId, ENT_QUOTES,'UTF-8'); ?>">
											<button type="button" class="btn btn-xs btn-success" id="btnSaveTech"><i class="fa fa-check"></i> Save</button>
											<button type="button" class="btn btn-xs btn-default" id="btnCancelTech"><i class="fa fa-times"></i></button>
										</span>
										<?php } ?>
									</td>
								</tr>
								<?php if ($isReported) { ?>
								<tr>
									<td width="12%"><label class="control-label">Actions:</label></td>
									<td width="88%">
										<?php if ($wfStatus === 'VERIFIED') { ?>
											<span class="label label-primary"><i class="fa fa-check-circle"></i> Supervisor Verified</span>
											<?php if ($verifiedBy !== '') { ?><span class="text-muted" style="margin-left:6px;"> by <?php echo htmlspecialchars($verifiedBy, ENT_QUOTES,'UTF-8'); ?></span><?php } ?>
										<?php } else if ($canSupVerify) { ?>
											<button type="button" class="btn btn-sm btn-primary" id="btnSuperVerify" data-io-lab-id="<?php echo $ioLabIdVal; ?>" data-module="<?php echo htmlspecialchars($moduleBase, ENT_QUOTES,'UTF-8'); ?>">
												<i class="fa fa-check-circle"></i> Supervisor Verify
											</button>
										<?php } ?>
										<?php if ($canDoctorAck) { ?>
										<button type="button" class="btn btn-sm btn-success" id="btnDoctorAck" data-io-lab-id="<?php echo $ioLabIdVal; ?>" data-module="<?php echo htmlspecialchars($moduleBase, ENT_QUOTES,'UTF-8'); ?>" style="margin-left:6px;">
											<i class="fa fa-thumbs-up"></i> Acknowledge Result
										</button>
										<?php if ($doctorAckAt !== '' && $doctorAckAt !== '0000-00-00 00:00:00') { ?>
										<span class="label label-success" style="margin-left:6px;"><i class="fa fa-check"></i> Acknowledged <?php echo date('M d, Y H:i', strtotime($doctorAckAt)); ?></span>
										<?php } ?>
										<?php } ?>
									</td>
								</tr>
								<?php } ?>
                                                    <tR>
                                                     	<td colspan="2">
                                                        <?php echo validation_errors(); ?>    
                                                        </td>

                                                    </tR>
                                                    

                                                    <tr>
                                                    	<td colspan="2">
															<div id="ref-ranges-panel" style="display:none;margin-bottom:8px;">
																<div class="panel panel-default" style="margin-bottom:0;">
																	<div class="panel-heading" style="padding:6px 10px;cursor:pointer;" id="ref-ranges-toggle">
																		<i class="fa fa-book"></i> <strong>Reference Ranges</strong>
																		<span class="pull-right"><i class="fa fa-chevron-down" id="ref-ranges-chevron"></i></span>
																	</div>
																	<div class="panel-body" id="ref-ranges-body" style="padding:6px 10px;">
																		<div id="ref-ranges-content"><i class="fa fa-spinner fa-spin"></i> Loading...</div>
																	</div>
																</div>
															</div>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                    	<td width="12%"><label class="control-label" for="findings">Findings:</label></td>
													<td width="88%"><textarea id="findings" name="findings" class="form-control" rows="3" <?php echo $isReadOnly ? 'readonly' : ''; ?>><?php echo htmlspecialchars($findingsVal, ENT_QUOTES, 'UTF-8'); ?></textarea></td>
                                                    </tr>
                                                    <tr>
													<td width="12%"><label class="control-label" for="result">Result: <font color="#FF0000">*</font></label></td>
                                                        <td width="88%">
														<textarea id="result" name="result" class="form-control" rows="3" <?php echo $isReadOnly ? 'readonly' : ''; ?>><?php echo htmlspecialchars($resultVal, ENT_QUOTES, 'UTF-8'); ?></textarea>
                                                        </td>
                                                    </tr>
                                                    
                                                    </table>
                                                    </div>
                                                </div>
                                                <div class="tab-pane" id="tab_structured">
													<?php if ($moduleBase === 'sonography'): ?>
													<!-- D3: Structured Sonography Report Template -->
													<?php if (!$isReadOnly): ?>
													<div class="alert alert-info" style="margin-bottom:10px;">
														<i class="fa fa-heartbeat"></i> <strong>Structured Sonography Report:</strong>
														Complete the relevant sections below. Leave unused sections blank.
													</div>
													<div id="sono-structured-form" class="table-responsive">
													<table class="table table-bordered" style="font-size:13px;">
														<tbody>
														<tr class="active"><td colspan="2"><strong><i class="fa fa-stethoscope"></i> Clinical Indication</strong></td></tr>
														<tr>
															<td width="25%"><label>Indication</label></td>
															<td><textarea name="sono_indication" id="sono_indication" class="form-control input-sm" rows="2" placeholder="Reason for scan / clinical question"></textarea></td>
														</tr>
														<tr class="active"><td colspan="2"><strong><i class="fa fa-medkit"></i> Organ Assessment</strong></td></tr>
														<tr>
															<td><label>Liver</label></td>
															<td>
																<div class="row"><div class="col-sm-4">
																	<select name="sono_liver_size" class="form-control input-sm">
																		<option value="">Size — Select</option>
																		<option>Normal</option><option>Enlarged</option><option>Small/Shrunken</option>
																	</select>
																</div><div class="col-sm-4">
																	<select name="sono_liver_echo" class="form-control input-sm">
																		<option value="">Echogenicity — Select</option>
																		<option>Normal</option><option>Increased</option><option>Decreased</option><option>Heterogeneous</option>
																	</select>
																</div><div class="col-sm-4">
																	<input type="text" name="sono_liver_notes" class="form-control input-sm" placeholder="Notes (e.g. lesion, fatty change)">
																</div></div>
															</td>
														</tr>
														<tr>
															<td><label>Gallbladder</label></td>
															<td>
																<div class="row"><div class="col-sm-4">
																	<select name="sono_gb_wall" class="form-control input-sm">
																		<option value="">Wall — Select</option>
																		<option>Normal</option><option>Thickened</option><option>Not visualised</option>
																	</select>
																</div><div class="col-sm-4">
																	<select name="sono_gb_stones" class="form-control input-sm">
																		<option value="">Calculi — Select</option>
																		<option>None</option><option>Single stone</option><option>Multiple stones</option><option>Sludge</option>
																	</select>
																</div><div class="col-sm-4">
																	<input type="text" name="sono_gb_notes" class="form-control input-sm" placeholder="CBD diameter (mm), notes">
																</div></div>
															</td>
														</tr>
														<tr>
															<td><label>Spleen</label></td>
															<td>
																<div class="row"><div class="col-sm-4">
																	<select name="sono_spleen" class="form-control input-sm">
																		<option value="">Size — Select</option>
																		<option>Normal</option><option>Splenomegaly</option><option>Small</option>
																	</select>
																</div><div class="col-sm-8">
																	<input type="text" name="sono_spleen_notes" class="form-control input-sm" placeholder="Span (cm), notes">
																</div></div>
															</td>
														</tr>
														<tr>
															<td><label>Right Kidney</label></td>
															<td>
																<div class="row"><div class="col-sm-3">
																	<input type="text" name="sono_rk_size" class="form-control input-sm" placeholder="Size (cm)">
																</div><div class="col-sm-3">
																	<select name="sono_rk_echo" class="form-control input-sm">
																		<option value="">Echo — Select</option>
																		<option>Normal</option><option>Increased</option><option>Decreased</option>
																	</select>
																</div><div class="col-sm-6">
																	<input type="text" name="sono_rk_notes" class="form-control input-sm" placeholder="Calculi, hydronephrosis, cysts, notes">
																</div></div>
															</td>
														</tr>
														<tr>
															<td><label>Left Kidney</label></td>
															<td>
																<div class="row"><div class="col-sm-3">
																	<input type="text" name="sono_lk_size" class="form-control input-sm" placeholder="Size (cm)">
																</div><div class="col-sm-3">
																	<select name="sono_lk_echo" class="form-control input-sm">
																		<option value="">Echo — Select</option>
																		<option>Normal</option><option>Increased</option><option>Decreased</option>
																	</select>
																</div><div class="col-sm-6">
																	<input type="text" name="sono_lk_notes" class="form-control input-sm" placeholder="Calculi, hydronephrosis, cysts, notes">
																</div></div>
															</td>
														</tr>
														<tr>
															<td><label>Urinary Bladder</label></td>
															<td>
																<div class="row"><div class="col-sm-4">
																	<select name="sono_bladder" class="form-control input-sm">
																		<option value="">Wall — Select</option>
																		<option>Normal</option><option>Thickened</option><option>Not distended</option>
																	</select>
																</div><div class="col-sm-4">
																	<input type="text" name="sono_bladder_pvr" class="form-control input-sm" placeholder="PVR (ml)">
																</div><div class="col-sm-4">
																	<input type="text" name="sono_bladder_notes" class="form-control input-sm" placeholder="Notes">
																</div></div>
															</td>
														</tr>
														<tr>
															<td><label>Uterus / Prostate</label></td>
															<td>
																<input type="text" name="sono_uterus_prostate" class="form-control input-sm" placeholder="Size (cm), echo, notes (e.g. prostate vol., uterine fibroid)">
															</td>
														</tr>
														<tr>
															<td><label>Other Findings</label></td>
															<td>
																<textarea name="sono_other" class="form-control input-sm" rows="2" placeholder="Ascites, masses, lymph nodes, aorta, IVC, other"></textarea>
															</td>
														</tr>
														<tr class="active"><td colspan="2"><strong><i class="fa fa-pencil-square-o"></i> Impression &amp; Conclusion</strong></td></tr>
														<tr>
															<td><label>Impression <font color="red">*</font></label></td>
															<td>
																<textarea name="sono_impression" id="sono_impression" class="form-control" rows="4" placeholder="Overall impression / conclusion / recommendation" required></textarea>
															</td>
														</tr>
														</tbody>
													</table>
													<button type="button" class="btn btn-info btn-sm" id="btnFillFromStructured">
														<i class="fa fa-arrow-left"></i> Copy to Findings/Result tab
													</button>
													</div>
													<?php else: ?>
													<div id="structured_existing_results">
														<div class="text-center" style="padding:20px;"><i class="fa fa-spinner fa-spin fa-2x"></i><br>Loading sonography report...</div>
													</div>
													<?php endif; ?>
													<?php else: ?>
													<!-- Generic Lab Structured Results (non-sonography) -->
													<?php if (!$isReadOnly): ?>
													<div class="alert alert-info" style="margin-bottom:10px;">
														<i class="fa fa-info-circle"></i> <strong>Structured Result Entry:</strong>
														Use dropdowns and color-coded fields below to enter results. Values are automatically flagged as Normal/High/Low/Critical.
														Results are saved via AJAX and the requesting doctor is notified automatically.
													</div>
													<div id="structured_template_container">
														<div class="text-center" style="padding:30px;"><i class="fa fa-spinner fa-spin fa-2x"></i><br>Loading template...</div>
													</div>
													<hr>
													<div id="structured_existing_results"></div>
													<?php else: ?>
													<div id="structured_existing_results">
														<div class="text-center" style="padding:30px;"><i class="fa fa-spinner fa-spin fa-2x"></i><br>Loading results...</div>
													</div>
													<?php endif; ?>
													<?php endif; ?>
												</div>
                                                <div class="tab-pane" id="tab_2">
												<iframe width="100%" frameborder="0" height="400" src="<?php echo base_url()?>app/<?php echo $moduleBase; ?>/upload_results/<?php echo $lab; ?>"></iframe>
												</div>
                                                
                                            </div>
                                        </div>
                                        
                                        
                                        
                               
                                
                            </div>
                            
                        </div>
                    </div>
                     </form>
                 </div>
                 
                 
                </section><!-- /.content -->
            </aside><!-- /.right-side -->
        </div><!-- ./wrapper -->
  
        
         <script src="<?php echo base_url();?>public/js/jquery.min.js"></script>
         <script src="<?php echo base_url();?>public/js/bootstrap.min.js" type="text/javascript"></script>     
        <script src="<?php echo base_url();?>public/js/AdminLTE/app.js" type="text/javascript"></script>
        <script src="<?php echo base_url(); ?>public/js/jquery-ui-1.10.3.min.js"></script>
        <link rel="stylesheet" href="<?php echo base_url(); ?>public/css/jQueryUI/jquery-ui-1.10.3.custom.min.css">
        <script src="<?php echo base_url();?>public/js/smart-medical-autocomplete.js"></script>
        
         <!-- BDAY -->
         <script src="<?php echo base_url();?>public/datepicker/js/jquery-1.9.1.min.js"></script>
        <script src="<?php echo base_url();?>public/datepicker/js/bootstrap-datepicker.js"></script>
        <script type="text/javascript">
            $(document).ready(function () {
                $('#birthday').datepicker({ format: "yyyy-mm-dd" });
            });
        </script>
        <!-- END BDAY -->

        <!-- B3/B7/A7 Workflow AJAX handlers -->
        <script type="text/javascript">
        $(document).ready(function() {
            var BASE_URL = '<?= base_url() ?>';
            var io_lab_id_wf = <?= (int)(isset($lab) ? $lab : 0) ?>;
            var csrfName = '<?php echo $this->security->get_csrf_token_name(); ?>';
            var csrfHash = '<?php echo $this->security->get_csrf_hash(); ?>';

            // B3 — Assign Technician
            $('#btnAssignTech').on('click', function() {
                $('#assignTechForm').show();
                $(this).hide();
            });
            $('#btnCancelTech').on('click', function() {
                $('#assignTechForm').hide();
                $('#btnAssignTech').show();
            });
            $('#btnSaveTech').on('click', function() {
                var techVal = $.trim($('#techInput').val());
                if (!techVal) { alert('Please enter a technician name or ID.'); return; }
                var moduleWf = '<?= htmlspecialchars(isset($module_base) ? $module_base : 'laboratory', ENT_QUOTES, 'UTF-8') ?>';
                var techData = { io_lab_id: io_lab_id_wf, technician_id: techVal };
                techData[csrfName] = csrfHash;
                $.post(BASE_URL + 'app/' + '<?= htmlspecialchars(isset($module_base) ? $module_base : 'laboratory', ENT_QUOTES, 'UTF-8') ?>' + '/assign_technician',
                    techData,
                    function(resp) {
                        if (resp && resp.ok) {
                            $('#techDisplay').html('<i class="fa fa-user"></i> ' + $('<div/>').text(techVal).html());
                            $('#assignTechForm').hide();
                            $('#btnAssignTech').show();
                        } else {
                            alert((resp && resp.message) ? resp.message : 'Failed to assign technician.');
                        }
                    }, 'json'
                ).fail(function() { alert('Server error assigning technician.'); });
            });

            // B7 — Supervisor Verify
            $('#btnSuperVerify').on('click', function() {
                var mod = $(this).data('module') || 'laboratory';
                var notes = prompt('Optional: Enter supervisor notes (leave blank to skip):') || '';
                if (!confirm('Mark this result as Supervisor Verified?')) return;
                var verifyData = { io_lab_id: io_lab_id_wf, notes: notes };
                verifyData[csrfName] = csrfHash;
                $.post(BASE_URL + 'app/' + mod + '/supervisor_verify',
                    verifyData,
                    function(resp) {
                        if (resp && resp.ok) {
                            location.reload();
                        } else {
                            alert((resp && resp.message) ? resp.message : 'Verification failed.');
                        }
                    }, 'json'
                ).fail(function() { alert('Server error during verification.'); });
            });

            // A7 — Doctor Acknowledge
            $('#btnDoctorAck').on('click', function() {
                var mod = $(this).data('module') || 'laboratory';
                if (!confirm('Acknowledge that you have reviewed this result?')) return;
                var ackData = { io_lab_id: io_lab_id_wf };
                ackData[csrfName] = csrfHash;
                $.post(BASE_URL + 'app/' + mod + '/doctor_acknowledge',
                    ackData,
                    function(resp) {
                        if (resp && resp.ok) {
                            location.reload();
                        } else {
                            alert((resp && resp.message) ? resp.message : 'Acknowledgement failed.');
                        }
                    }, 'json'
                ).fail(function() { alert('Server error during acknowledgement.'); });
            });
        });
        </script>

        <!-- Structured Results -->
        <script type="text/javascript">
        $(document).ready(function() {
            SmartMedical.init('<?= base_url() ?>');
            SmartMedical.injectStyles();

            var testName = '<?= addslashes(urldecode((string)(isset($lab_request_name) ? $lab_request_name : ""))) ?>';
            var ioLabId = <?= (int)(isset($lab) ? $lab : 0) ?>;
            var isReadOnly = <?= ($isReadOnly ? 'true' : 'false') ?>;

            // Load existing structured results
            if (ioLabId > 0) {
                $.ajax({
                    url: '<?= base_url() ?>app/medical_data/get_structured_results',
                    type: 'get',
                    dataType: 'json',
                    data: { io_lab_id: ioLabId },
                    success: function(data) {
                        if (data && data.length > 0) {
                            var html = '<h4><i class="fa fa-list"></i> Previously Saved Structured Results</h4>';
                            html += '<table class="table table-bordered table-hover table-striped">';
                            html += '<thead><tr><th>Parameter</th><th>Result</th><th>Unit</th><th>Normal Range</th><th>Status</th></tr></thead><tbody>';
                            for (var i = 0; i < data.length; i++) {
                                var r = data[i];
                                var flagLabel = r.result_flag || 'normal';
                                var flagCls = 'label-success';
                                if (r.color_code === 'red') flagCls = 'label-danger';
                                else if (r.color_code === 'orange') flagCls = 'label-warning';
                                else if (r.color_code === 'blue') flagCls = 'label-primary';
                                else if (r.color_code === 'yellow') flagCls = 'label-warning';
                                html += '<tr>';
                                html += '<td><strong>' + escHtml(r.parameter_name) + '</strong></td>';
                                html += '<td>' + escHtml(r.result_value) + '</td>';
                                html += '<td>' + escHtml(r.unit || '') + '</td>';
                                html += '<td>' + escHtml(r.normal_range || '') + '</td>';
                                html += '<td><span class="label ' + flagCls + '">' + escHtml(flagLabel) + '</span></td>';
                                html += '</tr>';
                            }
                            html += '</tbody></table>';
                            $('#structured_existing_results').html(html);
                        } else {
                            $('#structured_existing_results').html('');
                        }
                    }
                });
            }

            // Load template for entry (only if not read-only)
            if (!isReadOnly && testName !== '' && ioLabId > 0) {
                SmartMedical.loadLabTemplate(testName, '#structured_template_container', ioLabId);
            } else if (!isReadOnly) {
                $('#structured_template_container').html('<div class="alert alert-warning"><i class="fa fa-warning"></i> No test name detected. Please use the Text tab for manual entry.</div>');
            }

            function escHtml(str) {
                if (!str) return '';
                var div = document.createElement('div');
                div.appendChild(document.createTextNode(str));
                return div.innerHTML;
            }
        });
        </script>

        <!-- D2 — Reference Ranges -->
        <script type="text/javascript">
        $(document).ready(function() {
            var ioLabIdRR = <?= (int)(isset($lab) ? $lab : 0) ?>;
            var moduleRR  = '<?= htmlspecialchars(isset($module_base) ? $module_base : 'laboratory', ENT_QUOTES, 'UTF-8') ?>';

            if (ioLabIdRR > 0 && moduleRR !== 'sonography') {
                $.ajax({
                    url: '<?= base_url() ?>app/laboratory/get_ref_ranges',
                    type: 'get',
                    dataType: 'json',
                    data: { io_lab_id: ioLabIdRR },
                    success: function(resp) {
                        if (!resp || !resp.ok || !resp.ranges || resp.ranges.length === 0) return;
                        var ranges = resp.ranges;
                        var html = '<table class="table table-condensed table-bordered" style="margin-bottom:0;font-size:12px;">';
                        html += '<thead><tr><th>Parameter</th><th>Normal Low</th><th>Normal High</th><th>Unit</th></tr></thead><tbody>';
                        for (var i = 0; i < ranges.length; i++) {
                            var r = ranges[i];
                            html += '<tr>';
                            html += '<td>' + escRR(r.parameter) + '</td>';
                            html += '<td>' + (r.low !== '' ? escRR(r.low) : '<span class="text-muted">—</span>') + '</td>';
                            html += '<td>' + (r.high !== '' ? escRR(r.high) : '<span class="text-muted">—</span>') + '</td>';
                            html += '<td class="text-muted">' + escRR(r.unit) + '</td>';
                            html += '</tr>';
                        }
                        html += '</tbody></table>';
                        $('#ref-ranges-content').html(html);
                        $('#ref-ranges-panel').show();
                    }
                });
            }

            // Toggle collapse
            $('#ref-ranges-toggle').on('click', function() {
                $('#ref-ranges-body').slideToggle(150);
                $('#ref-ranges-chevron').toggleClass('fa-chevron-down fa-chevron-up');
            });

            function escRR(str) {
                if (!str && str !== 0) return '';
                var div = document.createElement('div');
                div.appendChild(document.createTextNode(String(str)));
                return div.innerHTML;
            }
        });
        </script>

        <!-- D3 — Sonography structured → text copy -->
        <script type="text/javascript">
        $(document).ready(function() {
            $('#btnFillFromStructured').on('click', function() {
                var lines = [];
                var val = function(sel) { return $.trim($(sel).val()); };

                var indication = val('#sono_indication');
                if (indication) lines.push('INDICATION: ' + indication);

                var sections = [
                    { label: 'LIVER',          size: 'sono_liver_size', echo: 'sono_liver_echo', notes: 'sono_liver_notes' },
                    { label: 'GALLBLADDER',    size: 'sono_gb_wall',    echo: 'sono_gb_stones',  notes: 'sono_gb_notes' },
                    { label: 'SPLEEN',         size: 'sono_spleen',     echo: null,              notes: 'sono_spleen_notes' },
                    { label: 'RIGHT KIDNEY',   size: 'sono_rk_size',    echo: 'sono_rk_echo',    notes: 'sono_rk_notes' },
                    { label: 'LEFT KIDNEY',    size: 'sono_lk_size',    echo: 'sono_lk_echo',    notes: 'sono_lk_notes' },
                    { label: 'URINARY BLADDER',size: 'sono_bladder',    echo: 'sono_bladder_pvr',notes: 'sono_bladder_notes' },
                    { label: 'UTERUS/PROSTATE',size: null,              echo: null,              notes: 'sono_uterus_prostate' },
                    { label: 'OTHER',          size: null,              echo: null,              notes: 'sono_other' }
                ];

                sections.forEach(function(s) {
                    var parts = [];
                    if (s.size && val('[name="' + s.size + '"]')) parts.push(val('[name="' + s.size + '"]'));
                    if (s.echo && val('[name="' + s.echo + '"]')) parts.push(val('[name="' + s.echo + '"]'));
                    if (s.notes && val('[name="' + s.notes + '"]')) parts.push(val('[name="' + s.notes + '"]'));
                    if (parts.length) lines.push(s.label + ': ' + parts.join(', '));
                });

                var impression = val('#sono_impression');

                var findings = lines.join('\n');
                var result   = impression ? 'IMPRESSION:\n' + impression : '';

                if (!findings && !result) {
                    alert('No structured data entered yet.');
                    return;
                }

                $('#findings').val(findings);
                $('#result').val(result);

                // Switch to Results (Text) tab
                $('a[href="#tab_1"]').tab('show');
            });
        });
        </script>

    </body>
</html>