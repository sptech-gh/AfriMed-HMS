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
                                		<li class="active"><a href="#tab_1" data-toggle="tab">Discharge Summary</a></li>
                                        
                                	</ul>
                                    <div class="tab-content">
                                    	<div class="tab-pane active" id="tab_1">
                                        	<div class="alert alert-info">Read-only — Doctor access only</div>
											<?php
											if(is_object($get_discharge_summary)){
												$reason_admission = $get_discharge_summary->reason_admission;
												$condition = $get_discharge_summary->condition_upon_discharge;
												$admitting_impression = $get_discharge_summary->admitting_impression;
												$final_diagnosis = $get_discharge_summary->final_diagnosis;
												$physical_exam_findings = $get_discharge_summary->physical_exam_findings;
												$course_ward = $get_discharge_summary->course_ward;
											}else{
												$reason_admission = "";
												$condition = "";
												$admitting_impression = "";
												$final_diagnosis = "";
												$physical_exam_findings = "";
												$course_ward = "";
											}
											?>
                                           
                                           <table class="table table-hover">
                                           <tbody>
                                            <tr>
                                	<td width="21%" valign="top">Reason for Admission</td>
                                	<td width="79%"><textarea name="reason_admission" id="reason_admission" class="form-control input-sm" style="width: 60%;" rows="3" readonly><?php echo $reason_admission?></textarea></td>
                                </tr>
                                <tr>
                                	<td valign="top">Condition Upon Discharge</td>
                                	<td>
                                    	<select name="condition" id="condition" class="form-control input-sm" style="width: 60%;" required disabled>
                                        	<option value="">- Condition Upon Discharge -</option>
                                        	<?php foreach($getConditionDis as $getConditionDis){?>
                                            <option value="<?php echo $getConditionDis->param_id?>" <?php if($getConditionDis->param_id == $condition){ echo "selected";}?>><?php echo $getConditionDis->cValue?></option>
                                            <?php }?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                	<td width="21%" valign="top">Admitting Impression</td>
                                	<td width="79%"><textarea name="admitting_impression" id="admitting_impression" class="form-control input-sm" style="width: 60%;" rows="3" readonly><?php echo $admitting_impression?></textarea></td>
                                </tr>
                                <tr>
                                	<td width="21%" valign="top">Final Diagnosis</td>
                                	<td width="79%"><textarea name="final_diagnosis" id="final_diagnosis" class="form-control input-sm" style="width: 60%;" rows="3" readonly><?php echo $final_diagnosis?></textarea></td>
                                </tr>
                                <tr>
                                	<td width="21%" valign="top">Clinical Findings</td>
                                	<td width="79%"><textarea name="physical_exam_findings" id="physical_exam_findings" class="form-control input-sm" style="width: 60%;" rows="3" readonly><?php echo $physical_exam_findings?></textarea></td>
                                </tr>
                                <tr>
                                	<td width="21%" valign="top">Course in the Ward</td>
                                	<td width="79%"><textarea name="course_ward" id="course_ward" class="form-control input-sm" style="width: 60%;" rows="3" readonly><?php echo $course_ward?></textarea></td>
                                </tr>
                                <tr>
                                           		<td colspan="2">
                                                
                                                <a href="<?php echo base_url()?>app/ipd_print/print_discharge_summary/<?php echo $getOPDPatient->IO_ID;?>/<?php echo $getOPDPatient->patient_no;?>" class="btn btn-default" target="_blank"><i class="fa fa-print"></i> Print</a>
                                                </td>
                                           </tr>
                                           </tbody>
                                           </table>
                                           </form>
                                            
                                            <br><br><br><br><br><br><br>
                                            <br><br><br><br><br><br><br>
                                            <br><br><br><br><br><br><br>
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