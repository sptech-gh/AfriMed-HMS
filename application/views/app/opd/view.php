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
                   <?php if($this->session->userdata('emr_viewing') == "opd_emr_viewing"){?>	
                   <h1>OPD Patient Information</h1>
                   <ol class="breadcrumb">
                        <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                        <li><a href="#">EMR sheet</a></li>
                        <li><a href="<?php echo base_url()?>app/emr/opd">Out-Patient Master</a></li>
                    </ol>
                    <?php }else if(!isset($hasAccesstoDoctor) || !$hasAccesstoDoctor){?>
                    <h1>OPD Patient Information</h1>
                   <ol class="breadcrumb">
                        <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                        <li><a href="#">Patient Management</a></li>
                        <li><a href="<?php echo base_url()?>app/opd/index">Out-Patient Master</a></li>
                        <li class="active">OPD Patient Information</li>
                    </ol>
                    <?php }else{?>
                    <h1>OPD Patient Information</h1>
                   <ol class="breadcrumb">
                        <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                        <li><a href="#">Doctor Module</a></li>
                        <li><a href="<?php echo base_url()?>app/doctor/opd">Out-Patient Master</a></li>
                        <li class="active">OPD Patient Information</li>
                    </ol>
                    <?php }?>
                </section>

                <!-- Main content -->
                <section class="content">

				<?php require_once(APPPATH.'views/app/encounter/partials/role_flags.php'); ?>

                <?php echo isset($message) ? $message : ''; ?>
                <?php echo $this->session->flashdata('nhis_warning') ? $this->session->flashdata('nhis_warning') : ''; ?>

				<?php require_once(APPPATH.'views/app/encounter/partials/status_banners.php'); ?>

                <?php require_once(APPPATH.'views/app/encounter/partials/non_owner_banner.php'); ?>
                 
        
                 
                 
        
                 
                 
                 <form method="post" action="<?php echo base_url();?>app/opd/save_opd" onSubmit="return confirm('Are you sure you want to save?');">
                 <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                 <input type="hidden" name="patient_no" value="<?php echo $patientInfo->patient_no?>">
                 <div class="row">
                 	
                     <div class="col-md-3">
                    	 <div class="box">
                         	 <div class="box-header"></div>
                        	<div class="box-body table-responsive no-padding">
                            	<?php $show_nhis_badge = true; require_once(APPPATH.'views/app/encounter/partials/patient_summary_card.php'); ?>
							</div>
							<div class="box-footer clearfix">
								<div style="margin-top: 15px;">
                            	<?php
                            	    $_nav_iop = $getOPDPatient ? $getOPDPatient->IO_ID : (isset($patientInfo->patient_no) ? '' : '');
                            	    $_nav_pno = $getOPDPatient ? $getOPDPatient->patient_no : (isset($patientInfo->patient_no) ? $patientInfo->patient_no : '');
                            	    $_nav_iop_safe = url_safe_id($_nav_iop);
                            	?>
                                 									<ul class="nav nav-pills nav-stacked">
										<li class="active"><a href="<?php echo base_url()?>app/opd/view/<?php echo $_nav_iop_safe;?>/<?php echo $_nav_pno;?>"> General Information</a></li>
										
									<?php if (!$isReception && !$isNurse) { ?>
									<li><a href="<?php echo base_url()?>app/opd/diagnosis/<?php echo $_nav_iop_safe;?>/<?php echo $_nav_pno;?>"> Diagnosis</a></li>
									<li><a href="<?php echo base_url()?>app/opd/medication/<?php echo $_nav_iop_safe;?>/<?php echo $_nav_pno;?>"> Medication</a></li>
									<li><a href="<?php echo base_url()?>app/opd/complain/<?php echo $_nav_iop_safe;?>/<?php echo $_nav_pno;?>"> Complain</a></li>
									<li><a href="<?php echo base_url()?>app/opd/vitalSign/<?php echo $_nav_iop_safe;?>/<?php echo $_nav_pno;?>"> Vital Sign</a></li>
									<li><a href="<?php echo base_url()?>app/opd/patientHistory/<?php echo $_nav_iop_safe;?>/<?php echo $_nav_pno;?>"> Patient History</a></li>
									<li><a href="<?php echo base_url()?>app/opd/laboratory/<?php echo $_nav_iop_safe;?>/<?php echo $_nav_pno;?>"> Laboratory</a></li>
									<li><a href="<?php echo base_url()?>app/opd/procedures/<?php echo $_nav_iop_safe;?>/<?php echo $_nav_pno;?>"> Procedures</a></li>
									<li><a href="<?php echo base_url()?>app/opd/discharge_summary/<?php echo $_nav_iop_safe;?>/<?php echo $_nav_pno;?>"> Discharge Summary</a></li>
									<?php require_once(APPPATH.'views/app/opd/_detain_admit_menu.php'); ?>
									<?php } ?>
									
									
									
									<!--<li><a href="<?php echo base_url()?>app/opd/billing/<?php echo $getOPDPatient->IO_ID;?>/<?php echo $getOPDPatient->patient_no;?>"> Admission Billing</a></li>-->
                                    
                                 </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                     
                     <div class="col-md-9"> 
                                <div class="nav-tabs-custom">
								<?php
								$encounter_tabs = array(
									array('id' => 'tab_1', 'label' => 'General Information', 'active' => true),
									array('id' => 'tab_timeline', 'label' => 'Timeline', 'active' => false),
								);
								require_once(APPPATH.'views/app/encounter/partials/encounter_tabs.php');
								?>
                                    <div class="tab-content">
	                                    	<div class="tab-pane active" id="tab_1">
                                        	<?php
                                        	$encounter_meta_rows = array();
                                        	$encounter_meta_empty_html = "<div class=\"alert alert-warning\"><i class=\"fa fa-exclamation-triangle\"></i> Visit record not found for this OPD ID.</div>";
                                        	if ($getOPDPatient) {
                                        		$encounter_meta_rows[] = array('label' => 'Date Visit', 'value' => date("M d, Y", strtotime($getOPDPatient->date_visit)));
                                        		$encounter_meta_rows[] = array('label' => 'Time Visit', 'value' => date("H:i:s", strtotime($getOPDPatient->time_visit)));
                                        		$encounter_meta_rows[] = array('label' => 'Referral Doctor', 'value' => $getOPDPatient->ref_doctor);
                                        		$encounter_meta_rows[] = array('label' => 'Department', 'value' => $getOPDPatient->dept_name);
                                        		$encounter_meta_rows[] = array('label' => 'Consultant Doctor', 'value' => $getOPDPatient->con_doctor);
                                        	}
                                        	require_once(APPPATH.'views/app/encounter/partials/encounter_meta.php');
                                        	?>

                                        	<?php
                                        	$vPayerType = isset($nhis_payer_type) ? strtoupper(trim((string)$nhis_payer_type)) : 'CASH';
                                        	$vIsReview = isset($nhis_is_review) ? (bool)$nhis_is_review : false;
                                        	$vClaim = isset($nhis_claim) ? $nhis_claim : null;
                                        	$vInv = isset($nhis_invoice) ? $nhis_invoice : null;
                                        	?>
	                                        	<div style="border-top:2px solid #3c8dbc; padding-top:10px; margin-top:5px;">
	                                            	<h4 style="color:#3c8dbc; margin-top:0;"><i class="fa fa-info-circle"></i> Visit Status</h4>
	                                            	<table class="table table-condensed" style="margin-bottom:5px;">
                                                	<tr>
                                                    	<td style="width:40%;"><strong>Payer</strong></td>
                                                    	<td>
                                                    		<?php if ($vPayerType === 'NHIS'): ?>
                                                    			<span class="label label-success"><i class="fa fa-medkit"></i> NHIS</span>
                                                    		<?php else: ?>
                                                    			<span class="label label-default"><i class="fa fa-money"></i> CASH</span>
                                                    		<?php endif; ?>
                                                    	</td>
                                                	</tr>
                                                	<?php
                                                	$vtEntry = isset($visit_type_entry) ? $visit_type_entry : null;
                                                	$vtLabel = ($vtEntry && isset($vtEntry->visit_type) && $vtEntry->visit_type !== '')
                                                		? $this->smart_billing_model->visit_type_label($vtEntry->visit_type)
                                                		: 'Unknown';
                                                	$vtWaived = ($vtEntry && !empty($vtEntry->consultation_waived));
                                                	$vtBadgeCls = ($vtEntry && isset($vtEntry->visit_type))
                                                		? $this->smart_billing_model->visit_type_badge_class($vtEntry->visit_type)
                                                		: 'label-default';
                                                	?>
                                                	<tr>
                                                    	<td><strong>Visit Type</strong></td>
                                                    	<td>
                                                    		<span class="label <?php echo $vtBadgeCls; ?>">
                                                    			<i class="fa fa-stethoscope"></i>
                                                    			<?php echo htmlspecialchars($vtLabel); ?>
                                                    		</span>
                                                    		<?php if ($vtWaived): ?>
                                                    			<span class="label label-success" style="font-size:10px; margin-left:4px;">Consult waived</span>
                                                    		<?php endif; ?>
                                                    	</td>
                                                	</tr>
                                                	<?php if ($vPayerType === 'NHIS'): ?>
                                                	<tr>
                                                    	<td><strong>Review Visit</strong></td>
                                                    	<td>
                                                    		<?php if ($vIsReview): ?>
                                                    			<span class="label label-info"><i class="fa fa-refresh"></i> Yes (No consult fee)</span>
                                                    		<?php else: ?>
                                                    			<span class="text-muted">No</span>
                                                    		<?php endif; ?>
                                                    	</td>
                                                	</tr>
                                                	<?php endif; ?>
                                                	<tr>
                                                    	<td><strong>Payment</strong></td>
                                                    	<td>
                                                    		<?php if ($vInv): ?>
                                                    			<span class="label label-primary"><?php echo htmlspecialchars($vInv->invoice_no); ?></span>
                                                    			<small>GHS <?php echo number_format((float)$vInv->total_amount, 2); ?></small>
                                                    			<?php if ($vPayerType === 'NHIS' && isset($vInv->nhis_covered_amount) && (float)$vInv->nhis_covered_amount > 0): ?>
                                                    				<br><small class="text-success">NHIS: <?php echo number_format((float)$vInv->nhis_covered_amount, 2); ?></small>
                                                    				<small class="text-danger">Patient: <?php echo number_format((float)$vInv->patient_payable_amount, 2); ?></small>
                                                    			<?php endif; ?>
                                                    		<?php else: ?>
                                                    			<span class="text-warning"><i class="fa fa-clock-o"></i> No invoice yet</span>
                                                    		<?php endif; ?>
                                                    	</td>
                                                	</tr>
                                                	<?php if ($vClaim): ?>
                                                	<tr>
                                                    	<td><strong>NHIS Claim</strong></td>
                                                    	<td>
                                                    		<?php
                                                    		$clStatus = strtoupper(trim((string)$vClaim->status));
                                                    		$clLabel = 'default';
                                                    		if ($clStatus === 'PENDING') $clLabel = 'warning';
                                                    		elseif ($clStatus === 'SUBMITTED') $clLabel = 'info';
                                                    		elseif ($clStatus === 'APPROVED') $clLabel = 'success';
                                                    		elseif ($clStatus === 'REJECTED') $clLabel = 'danger';
                                                    		?>
                                                    		<span class="label label-<?php echo $clLabel; ?>"><?php echo htmlspecialchars($vClaim->claim_ref); ?> - <?php echo $clStatus; ?></span>
                                                    	</td>
                                                	</tr>
                                                	<?php endif; ?>
                                                </table>
    	                                        	</div>
									</div>
									<div class="tab-pane" id="tab_timeline">
										<?php require_once(APPPATH.'views/app/encounter/partials/timeline.php'); ?>
	                                        </div>
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

        <!-- Detain Patient Modal -->
        <div class="modal fade" id="modalDetainPatient" tabindex="-1" role="dialog">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header bg-yellow">
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                        <h4 class="modal-title"><i class="fa fa-clock-o"></i> Detain Patient (Observation)</h4>
                    </div>
                    <div class="modal-body">
                        <div id="detainAlert"></div>
                        <p class="text-muted"><i class="fa fa-info-circle"></i> Detention is free until midnight. If the patient remains after 12:00am, the system will convert this OPD visit to an IPD admission for ward billing.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-warning" id="btnConfirmDetain"><i class="fa fa-check"></i> Confirm Detention</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Admit Patient Modal -->
        <div class="modal fade" id="modalAdmitPatient" tabindex="-1" role="dialog">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header bg-red">
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                        <h4 class="modal-title"><i class="fa fa-hospital-o"></i> Queue Patient for IPD Admission</h4>
                    </div>
                    <div class="modal-body">
                        <div id="admitAlert"></div>
                        <div class="form-group">
                            <label>Admission Reason / Clinical Indication</label>
                            <textarea id="admitReason" class="form-control" rows="3" placeholder="Enter reason for admission..."></textarea>
                        </div>
                        <p class="text-muted"><i class="fa fa-info-circle"></i> This will notify IPD Registration staff to assign a ward and bed.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-danger" id="btnConfirmAdmit"><i class="fa fa-bed"></i> Confirm Admission</button>
                    </div>
                </div>
            </div>
        </div>

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
            
                $('#btnConfirmDetain').on('click', function () {
					var btn = $(this).prop('disabled', true).text('Submitting...');
					$.post('<?php echo base_url(); ?>app/opd/detain_patient_ajax', {
						'<?php echo $this->security->get_csrf_token_name(); ?>': '<?php echo $this->security->get_csrf_hash(); ?>',
						iop_id: '<?php echo isset($getOPDPatient->IO_ID) ? htmlspecialchars((string)$getOPDPatient->IO_ID, ENT_QUOTES) : ''; ?>',
						patient_no: '<?php echo isset($getOPDPatient->patient_no) ? htmlspecialchars((string)$getOPDPatient->patient_no, ENT_QUOTES) : ''; ?>'
					}, function (res) {
						if (res && res.ok) {
							$('#detainAlert').html('<div class="alert alert-success"><i class="fa fa-check"></i> Patient marked as detained.</div>');
							btn.text('Done');
							setTimeout(function(){ window.location.reload(); }, 700);
						} else {
							$('#detainAlert').html('<div class="alert alert-danger">' + (res ? res.error : 'Error') + '</div>');
							btn.prop('disabled', false).text('Confirm Detention');
						}
					}, 'json').fail(function (xhr) {
						var msg = 'Request failed. Please try again.';
						if (xhr.status === 403) { msg = 'Session expired. Please refresh the page and try again.'; }
						$('#detainAlert').html('<div class="alert alert-danger">' + msg + '</div>');
						btn.prop('disabled', false).text('Confirm Detention');
					});
				});

				$('#btnConfirmAdmit').on('click', function () {
					var reason = $.trim($('#admitReason').val());
					if (reason === '') { $('#admitAlert').html('<div class="alert alert-warning">Please enter an admission reason.</div>'); return; }
					var btn = $(this).prop('disabled', true).text('Submitting...');
					$.post('<?php echo base_url(); ?>app/opd/admit_patient_from_opd', {
						'<?php echo $this->security->get_csrf_token_name(); ?>': '<?php echo $this->security->get_csrf_hash(); ?>',
                        iop_id: '<?php echo isset($getOPDPatient->IO_ID) ? htmlspecialchars((string)$getOPDPatient->IO_ID, ENT_QUOTES) : ''; ?>',
                        patient_no: '<?php echo isset($getOPDPatient->patient_no) ? htmlspecialchars((string)$getOPDPatient->patient_no, ENT_QUOTES) : ''; ?>',
                        reason: reason,
                        doctor_id: '<?php echo (string)$this->session->userdata('user_id'); ?>'
                    }, function (res) {
                        if (res && res.ok) {
                            $('#admitAlert').html('<div class="alert alert-success"><i class="fa fa-check"></i> ' + res.message + '</div>');
                            btn.text('Done');
                        } else {
                            $('#admitAlert').html('<div class="alert alert-danger">' + (res ? res.error : 'Error') + '</div>');
                            btn.prop('disabled', false).text('Confirm Admission');
                        }
                    }, 'json').fail(function (xhr, status, error) {
                        var msg = 'Request failed. Please try again.';
                        if (xhr.status === 403) { msg = 'Session expired. Please refresh the page and try again.'; }
                        $('#admitAlert').html('<div class="alert alert-danger">' + msg + '</div>');
                        btn.prop('disabled', false).text('Confirm Admission');
                    });
                });
            });
        </script>
        <!-- END BDAY -->
        
        
    </body>
</html>
