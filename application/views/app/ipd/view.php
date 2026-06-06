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
					<?php if($this->session->userdata('emr_viewing') == "ipd_emr_viewing"){?>	
                   <h1>IPD Patient Information</h1>
                   <ol class="breadcrumb">
                        <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                        <li><a href="#">EMR sheet</a></li>
                        <li><a href="<?php echo base_url()?>app/emr/opd">In-Patient</a></li>
                    </ol>
                    <?php }else if(!isset($hasAccesstoDoctor) || !$hasAccesstoDoctor){?>
                    <h1>IPD Patient Information</h1>
                   <ol class="breadcrumb">
                        <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                        <li><a href="#">Patient Management</a></li>
                        <li><a href="<?php echo base_url()?>app/ipd/index">In-Patient Master</a></li>
                        <li><a href="#">In-Patient Information</a></li>
                    </ol>
                    <?php }else{?>
                    <h1>IPD Patient Information</h1>
                   <ol class="breadcrumb">
                        <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                        <li><a href="#">Doctor Module</a></li>
                        <li><a href="<?php echo base_url()?>app/doctor/ipd">In-Patient Master</a></li>
                        <li><a href="#">In-Patient Information</a></li>
                    </ol>
                    <?php }?>
                    
                </section>

                <!-- Main content -->
                <section class="content">

				<?php require_once(APPPATH.'views/app/encounter/partials/role_flags.php'); ?>

                <?php echo isset($message) ? $message : ''; ?>
				<?php require_once(APPPATH.'views/app/encounter/partials/status_banners.php'); ?>
				<?php if (!isset($patientInfo) || !$patientInfo || !isset($getOPDPatient) || !$getOPDPatient) { ?>
					<div class="alert alert-warning">
						Unable to load IPD patient information.
					</div>
				<?php return; } ?>

                <?php require_once(APPPATH.'views/app/encounter/partials/non_owner_banner.php'); ?>
                 
        
                 
                 <form method="post" action="<?php echo base_url();?>app/opd/save_opd" onSubmit="return confirm('Are you sure you want to save?');">
                 <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                 <input type="hidden" name="patient_no" value="<?php echo $patientInfo->patient_no?>">
                 <div class="row">
                 	
                     <div class="col-md-3">
                    	 <div class="box">
                         	 <div class="box-header"></div>
                        							<div class="box-body table-responsive no-padding">
								<?php require_once(APPPATH.'views/app/encounter/partials/patient_summary_card.php'); ?>
							</div>
						<div class="box-footer clearfix">
							<div style="margin-top: 15px;">
														<ul class="nav nav-pills nav-stacked">
								<li class="active"><a href="<?php echo base_url()?>app/ipd/view/<?php echo $getOPDPatient->IO_ID;?>/<?php echo $getOPDPatient->patient_no;?>"> General Information</a></li>
								<?php if (!$isReception && !$isNurse) { ?>
								<li><a href="<?php echo base_url()?>app/ipd/diagnosis/<?php echo $getOPDPatient->IO_ID;?>/<?php echo $getOPDPatient->patient_no;?>"> Diagnosis</a></li>
									<li><a href="<?php echo base_url()?>app/ipd/medication/<?php echo $getOPDPatient->IO_ID;?>/<?php echo $getOPDPatient->patient_no;?>"> Medication</a></li>
									<li><a href="<?php echo base_url()?>app/ipd/complain/<?php echo $getOPDPatient->IO_ID;?>/<?php echo $getOPDPatient->patient_no;?>"> Complain</a></li>
									<li><a href="<?php echo base_url()?>app/ipd/progress_note/<?php echo $getOPDPatient->IO_ID;?>/<?php echo $getOPDPatient->patient_no;?>"> Progress Note</a></li>
									<li><a href="<?php echo base_url()?>app/ipd/bed_side_procedure/<?php echo $getOPDPatient->IO_ID;?>/<?php echo $getOPDPatient->patient_no;?>"> Bed Side Procedure</a></li>
									<li><a href="<?php echo base_url()?>app/ipd/operation_theater/<?php echo $getOPDPatient->IO_ID;?>/<?php echo $getOPDPatient->patient_no;?>"> Operation Theater</a></li>
									<li><a href="<?php echo base_url()?>app/ipd/patientHistory/<?php echo $getOPDPatient->IO_ID;?>/<?php echo $getOPDPatient->patient_no;?>"> Patient History</a></li>
									<li><a href="<?php echo base_url()?>app/ipd/laboratory/<?php echo $getOPDPatient->IO_ID;?>/<?php echo $getOPDPatient->patient_no;?>"> Laboratory</a></li>
									<li><a href="<?php echo base_url()?>app/ipd/discharge_summary/<?php echo $getOPDPatient->IO_ID;?>/<?php echo $getOPDPatient->patient_no;?>"> Discharge Summary</a></li>
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
                                        	$encounter_meta_empty_html = '';
                                        	$encounter_meta_rows[] = array('label' => 'Date Admit', 'value' => date("M d, Y", strtotime($getOPDPatient->date_visit)));
                                        	$encounter_meta_rows[] = array('label' => 'Time Admit', 'value' => date("H:i:s A", strtotime($getOPDPatient->time_visit)));
                                        	$encounter_meta_rows[] = array('label' => 'In-Charge Doctor', 'value' => $getOPDPatient->con_doctor);
                                        	$encounter_meta_rows[] = array('label' => 'Department', 'value' => $getOPDPatient->dept_name);
                                        	$encounter_meta_rows[] = array('label' => 'Room', 'value' => $getOPDPatient->room_name);
	                                        	$encounter_meta_rows[] = array('label' => 'Bed No.', 'value' => $getOPDPatient->bed_name);
	                                        	require_once(APPPATH.'views/app/encounter/partials/encounter_meta.php');
	                                        	?>
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