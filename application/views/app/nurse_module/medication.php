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
           
   <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/css/select2.min.css" rel="stylesheet" />
        
        <!-- jQuery UI CSS -->
        <link rel="stylesheet" href="<?php echo base_url(); ?>public/css/jQueryUI/jquery-ui-1.10.3.custom.min.css">
    
        <style>
            .ui-autocomplete { position: absolute; cursor: default;z-index:999999999 !important;}
        </style>
         
        
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
                    <h1>IPD Patient Information</h1>
                   <ol class="breadcrumb">
                        <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                        <li><a href="#">Nurse Module</a></li>
                        <li><a href="#">In-Patient Information</a></li>
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
                                        </table>
                                    </td>
                                </tr>
                                </table>
                            </div>
                            <div class="box-footer clearfix">
                            	<table class="table">
                                <tr>
                                	<td><u>IOP No.</u></td>
                                </tr>
                                <tr>
                                	<td><?php echo $getOPDPatient->IO_ID;?></td>
                                </tr>
                                <tr>
                                	<td><u>Date Time Admit</u></td>
                                </tr>
                                <tr>
                                	<td><?php echo date("M d, Y", strtotime($getOPDPatient->date_visit));?>&nbsp;<?php echo date("H:i:s A", strtotime($getOPDPatient->time_visit));?></td>
                                </tr>
                                <tr>
                                	<td><u>In-Charge Doctor</u></td>
                                </tr>
                                <tr>
                                	<td><?php echo $getOPDPatient->con_doctor;?></td>
                                </tr>
                                <tr>
                                	<td><u>Department</u></td>
                                </tr>
                                <tr>
                                	<td><?php echo $getOPDPatient->dept_name;?></td>
                                </tr>
                                <tr>
                                	<td><u>Room</u></td>
                                </tr>
                                <tr>
                                	<td><?php echo $getOPDPatient->room_name;?></td>
                                </tr>
                                <tr>
                                	<td><u>Bed No.</u></td>
                                </tr>
                                <tr>
                                	<td><?php echo $getOPDPatient->bed_name;?></td>
                                </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                     
                     <div class="col-md-9"> 
                                <div class="nav-tabs-custom">
                                	<ul class="nav nav-tabs">
                                		<li class="active"><a href="#tab_1" data-toggle="tab">Medication</a></li>
                                        
                                	</ul>
                                    <div class="tab-content">
                                    	<div class="tab-pane active" id="tab_1">
											
                                            <?php echo $message;?>
										<?php if (isset($nurse_enhancements_ready) && !$nurse_enhancements_ready): ?>
											<div class="alert alert-warning">
												<i class="fa fa-warning"></i>
												Nurse enhancements are not installed. Please ask an Administrator to run <strong>app/nurse_module/install_enhancements</strong>.
											</div>
										<?php endif; ?>
											
										<a href="<?php echo base_url()?>app/ipd_print/print_medication/<?php echo $getOPDPatient->IO_ID;?>/<?php echo $getOPDPatient->patient_no;?>" class="btn btn-default" target="_blank"><i class="fa fa-print"></i> Print</a>
										<table class="table table-hover table-striped">
										<thead>
												<tr>
													<th>Medicine Name</th>
													<th>Frequency</th>
													<th>Instruction</th>
													<th>Advice</th>
													<th>Days</th>
													<th>Qty</th>
													<?php if (isset($nurse_enhancements_ready) && $nurse_enhancements_ready): ?>
														<th>Last Admin</th>
														<th>Admin</th>
													<?php endif; ?>
													<th>Prepared by</th>
													<th></th>
												</tr>
									</thead>
                                           <tbody>
										   <?php if(empty($patientMedication)): ?>
                                           <tr>
                                           		<td colspan="<?php echo (isset($nurse_enhancements_ready) && $nurse_enhancements_ready) ? '10' : '8'; ?>" style="text-align:center; padding:30px;">
													<div style="color:#95a5a6;">
														<i class="fa fa-medkit" style="font-size:36px;"></i><br><br>
														<strong>No IPD prescriptions yet</strong><br>
														<span style="font-size:12px;">The attending doctor must prescribe medications under this IPD encounter.<br>Check the OPD Reference panel below for prior outpatient prescriptions.</span>
													</div>
												</td>
                                           </tr>
										   <?php else: ?>
                                           <?php foreach($patientMedication as $rows){?>
                                           <?php $medName = trim((string)$rows->drug_name); $medText = isset($rows->medicine_text) ? trim((string)$rows->medicine_text) : ''; $medDisplay = $medName !== '' ? $medName : $medText; ?>
                                           <tr>
                                           		<td><?php echo htmlspecialchars($medDisplay); ?></td>
                                                <td><?php echo isset($rows->frequency) ? htmlspecialchars((string)$rows->frequency) : ''; ?></td>
                                                <td><?php echo $rows->instruction?></td>
                                                <td><?php echo $rows->advice?></td>
                                                <td><?php echo $rows->days?></td>
                                                <td><?php echo $rows->total_qty?></td>
											<?php if (isset($nurse_enhancements_ready) && $nurse_enhancements_ready): ?>
												<td>
													<?php
													$last = (isset($adminLatestByMed) && isset($adminLatestByMed[(string)$rows->iop_med_id])) ? $adminLatestByMed[(string)$rows->iop_med_id] : null;
													if ($last) {
														$labelClass = 'label-default';
														if (strtoupper($last->status) === 'GIVEN') { $labelClass = 'label-success'; }
														if (strtoupper($last->status) === 'HELD') { $labelClass = 'label-warning'; }
														if (strtoupper($last->status) === 'REFUSED') { $labelClass = 'label-danger'; }
														echo '<span class="label '.$labelClass.'">'.htmlspecialchars($last->status).'</span>';
														echo '<div class="text-muted" style="font-size:11px;">'.htmlspecialchars($last->dDateTime).'</div>';
													} else {
														echo '<span class="text-muted">—</span>';
													}
													?>
												</td>
												<td>
													<a href="#" class="btn btn-xs btn-success" data-toggle="modal" data-target="#adminModal" data-med-id="<?php echo $rows->iop_med_id; ?>" data-med-name="<?php echo htmlspecialchars($medDisplay); ?>" data-med-dosage="<?php echo htmlspecialchars(isset($rows->dosage) ? $rows->dosage : ''); ?>" data-med-freq="<?php echo htmlspecialchars(isset($rows->frequency) ? $rows->frequency : ''); ?>"><i class="fa fa-check-square"></i> Administer</a>
												</td>
											<?php endif; ?>
											<td><?php echo $rows->name?></td>
									</tr>
									<?php }?>
									<?php endif; ?>
                                           </tbody>
                                           </table>

										<?php if (isset($opdMedications) && !empty($opdMedications)): ?>
										<div class="box box-warning" style="margin-top:15px;">
											<div class="box-header with-border">
												<h3 class="box-title"><i class="fa fa-history"></i> OPD Prescription Reference</h3>
												<span class="label label-warning pull-right">Read-only Reference</span>
											</div>
											<div class="box-body">
												<div class="alert alert-info" style="font-size:12px;">
													<i class="fa fa-info-circle"></i>
													These medications were prescribed during the patient's OPD visit. They cannot be administered here.
													<strong>Ask the attending doctor to re-prescribe under this IPD encounter</strong> if these medications should continue during admission.
												</div>
												<table class="table table-condensed table-striped">
												<thead><tr><th>Visit</th><th>Medicine</th><th>Dosage</th><th>Frequency</th><th>Days</th><th>Qty</th><th>Prescribed By</th><th>Date</th></tr></thead>
												<tbody>
												<?php foreach($opdMedications as $opd_med): ?>
												<tr>
													<td><span class="label label-default"><?php echo htmlspecialchars($opd_med->iop_id); ?></span></td>
													<td><strong><?php echo htmlspecialchars($opd_med->drug_name ?: $opd_med->medicine_text); ?></strong></td>
													<td><?php echo htmlspecialchars(isset($opd_med->dosage) ? $opd_med->dosage : ''); ?></td>
													<td><?php echo htmlspecialchars(isset($opd_med->frequency) ? $opd_med->frequency : ''); ?></td>
													<td><?php echo (int)$opd_med->days; ?></td>
													<td><?php echo (int)$opd_med->total_qty; ?></td>
													<td><?php echo htmlspecialchars($opd_med->prescribed_by_name); ?></td>
													<td><?php echo date('M d, H:i', strtotime($opd_med->dDate)); ?></td>
												</tr>
												<?php endforeach; ?>
												</tbody>
												</table>
											</div>
										</div>
										<?php endif; ?>
                                        </div>
                           			</div>
                            <div class="box-footer clearfix">
                                	
                            </div>
                        </div>
                    </div>
                 </div>
                 
                </section><!-- /.content -->
            </aside><!-- /.right-side -->
        </div><!-- ./wrapper -->

						<?php if (isset($nurse_enhancements_ready) && $nurse_enhancements_ready): ?>
							<?php echo form_open('app/nurse_module/save_medication_admin', array('onSubmit' => "return confirm('Are you sure you want to save?');")); ?>
								<input type="hidden" name="opd_no" value="<?php echo $getOPDPatient->IO_ID?>">
								<input type="hidden" name="patient_no" value="<?php echo $getOPDPatient->patient_no?>">
								<input type="hidden" name="iop_med_id" id="admin_iop_med_id" value="">
								<div class="modal fade" id="adminModal" tabindex="-1" role="dialog" aria-labelledby="adminModalLabel" aria-hidden="true">
									<div class="modal-dialog">
										<div class="modal-content">
											<div class="modal-header">
												<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
												<h4 class="modal-title" id="adminModalLabel">Medication Administration</h4>
											</div>
											<div class="modal-body">
												<div class="form-group">
													<label>Medication</label>
													<div class="form-control" style="height:auto;" id="admin_med_name"></div>
												</div>
												<div class="form-group">
													<label>Status</label>
													<select name="status" class="form-control" required>
														<option value="GIVEN">GIVEN</option>
														<option value="HELD">HELD</option>
														<option value="REFUSED">REFUSED</option>
													</select>
												</div>
												<div class="form-group">
													<label>Date/Time</label>
													<input type="text" class="form-control" name="dDateTime" value="<?php echo date('Y-m-d H:i:s'); ?>">
												</div>
												<div class="form-group">
													<label>Dose Given (optional)</label>
													<input type="text" class="form-control" name="dose_given" placeholder="e.g. 1 tab, 5ml">
												</div>
												<div class="form-group">
													<label>Notes (optional)</label>
													<textarea name="notes" class="form-control" rows="3" placeholder="Vitals, side effects, reason held/refused..."></textarea>
												</div>
											</div>
											<div class="modal-footer">
												<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
												<button type="submit" class="btn btn-primary">Save</button>
											</div>
										</div>
									</div>
								</div>
							<?php echo form_close(); ?>
						<?php endif; ?>
  
        
         <script src="<?php echo base_url();?>public/js/jquery.min.js"></script>
         <script src="<?php echo base_url();?>public/js/bootstrap.min.js" type="text/javascript"></script>     
        <script src="<?php echo base_url();?>public/js/AdminLTE/app.js" type="text/javascript"></script>
         
   						<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/js/select2.min.js"></script>
    <!-- jQuery UI -->
    <script src="<?php echo base_url(); ?>public/js/jquery-ui-1.10.3.min.js"></script>
      
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
        
							<!-- Nurse prescribing disabled — administration only -->
							<!-- Medication Modal (Phase 3) intentionally NOT loaded for nurse role -->
							<?php if (isset($nurse_enhancements_ready) && $nurse_enhancements_ready): ?>
							<script>
							$(document).ready(function(){
								$('#adminModal').on('show.bs.modal', function(e){
									var btn = $(e.relatedTarget);
									$('#admin_iop_med_id').val(btn.data('med-id'));
									$('#admin_med_name').text(btn.data('med-name'));
									var dosage = btn.data('med-dosage') || '';
									var freq = btn.data('med-freq') || '';
									if(dosage) $('#admin_med_dosage').text('Dosage: ' + dosage + (freq ? ' | Frequency: ' + freq : ''));
								});
							});
							</script>
							<?php endif; ?>
        

                
        
        
    </body>
</html>