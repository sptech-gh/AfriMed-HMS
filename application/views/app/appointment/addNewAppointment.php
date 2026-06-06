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
        <link href="<?php echo base_url();?>public/css/timepicker/bootstrap-timepicker.css" rel="stylesheet" type="text/css" />
        
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
                    <h1>Patient Registration</h1>
                    <ol class="breadcrumb">
                        <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                        <li><a href="#">Patient Management</a></li>
                        <li class="active">Patient Registration</li>
                    </ol>
                </section>

                <!-- Main content -->
                <section class="content">
                 
                 <style>
                 .field-error { border-color: #dd4b39 !important; background-color: #fff5f5 !important; }
                 .validation-summary { margin-bottom: 15px; }
                 .validation-summary ul { margin: 5px 0 0 0; padding-left: 20px; }
                 .validation-summary li { margin-bottom: 3px; }
                 .required-note { color: #999; font-size: 12px; margin-bottom: 10px; }
                 </style>
                 <?php 
                 $flashMsg = $this->session->flashdata('message');
                 if ($flashMsg) { echo $flashMsg; }
                 ?>
                 <script language="javascript">
                 function validate(){
                    var allFields = document.querySelectorAll('.field-error');
                    for (var i = 0; i < allFields.length; i++) allFields[i].classList.remove('field-error');

                    var errors = [];
                    var required = [
                        {id: 'title', label: 'Title'},
                        {id: 'lastname', label: 'Surname'},
                        {id: 'firstname', label: 'First Name'},
                        {id: 'dateAppointment', label: 'Appointment Date'},
                        {id: 'appHour', label: 'Appointment Hour'},
                        {id: 'appMinutes', label: 'Appointment Minutes'},
                        {id: 'appAMPM', label: 'AM/PM'},
                        {id: 'consultantDoctor', label: 'Consultant Doctor'}
                    ];
                    for (var i = 0; i < required.length; i++) {
                        var el = document.getElementById(required[i].id);
                        if (el && !el.value.trim()) {
                            errors.push(required[i].label + ' is required');
                            el.classList.add('field-error');
                        }
                    }

                    if (errors.length > 0) {
                        var summaryHtml = "<div class='alert alert-danger alert-dismissable validation-summary'>" +
                            "<i class='fa fa-exclamation-circle'></i> " +
                            "<button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>" +
                            "<strong>Please fix the following errors:</strong><ul>";
                        for (var i = 0; i < errors.length; i++) {
                            summaryHtml += '<li>' + errors[i] + '</li>';
                        }
                        summaryHtml += '</ul></div>';
                        var container = document.getElementById('validation-msg');
                        if (container) container.innerHTML = summaryHtml;
                        window.scrollTo(0, 0);
                        return false;
                    }

                    return confirm('Are you sure you want to save this appointment?');
                 }
                 </script>
                 <div class="row">
                 	<div class="col-md-12">
                    <form role="form" method="post" action="<?php echo base_url()?>app/appointment/save" onSubmit="return validate()">    
                    <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                    	 <div class="box">
                         		
                         		<div class="box-footer clearfix">
                            	   <button class="btn btn-primary" name="btnSubmit" id="btnSubmit" type="submit"><i class="fa fa-save"></i> Save Appointment</button>
                                </div>
                                <div id="validation-msg"></div>
                            
                        	<div class="box-body table-responsive">
                            	
                                
                                		<table cellpadding="3" cellspacing="3" width="100%">
                                                    <tr>
                                                        <td colspan="2">
                                                        <span class="required-note"><font color="#FF0000">*</font> indicates a required field</span>
                                                        <?php echo validation_errors(); ?>
                                                        </td>
                                                    </tr>
                                                    <?php

                                                    $readonly = "";
                                                    $title_name = "";
                                                    $lastname_pick = "";
                                                    $firstname_pick = "";
                                                    $middlename_pick = "";
                                                    $gender_pick = "";

                                                    if(isset($this->data['mode']))
                                                    {
                                                        $userID2 =  $patientInfo->patient_no;
                                                        $userID =  $patientInfo->patient_no;
                                                        $readonly = "readonly";
                                                        $mode = "pick";

                                                        $title_name = $patientInfo->title;
                                                        $lastname_pick = $patientInfo->lastname;
                                                        $firstname_pick = $patientInfo->firstname;
                                                        $middlename_pick = $patientInfo->middlename;
                                                        $gender_pick = $patientInfo->gender;
                                                    }
                                                    else
                                                    {
                                                        $userID = $lastPatientID->patient_no;
                                                        $userID2 = $lastPatientID->patient_no;
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

                                                        $mode = "new";
                                                    }

                                                    
                                                    ?>
                                                    <input type="hidden" name="userID2" value="<?php echo $userID2;?>">
                                                    <input type="hidden" name="mode" id="mode" value="<?php echo $mode;?>">
                                                    <tr>
                                                        <td width="12%">Patient ID</td>
                                                        <td width="88%"><input class="form-control input-sm" name="patientID" id="patientID" type="text" style="width: 100px;" required readonly value="<?php echo $userID;?>"></td>
                                                    </tr>
                                                    <tr>
                                                        <td width="12%">Title <font color="#FF0000">*</font></td>
                                                        <td width="88%">
                                                            <select name="title" id="title" class="form-control input-sm" style="width: 100px;" <?php echo $readonly;?>>
                                                                <option value="">- Title -</option>
                                                                <?php 
                                                                foreach($UserTitles as $UserTitles){
                                                                if($title_name == $UserTitles->param_id){
                                                                    $selected = "selected='selected'";
                                                                }else{
                                                                    $selected = "";
                                                                }
                                                                ?>
                                                                <option value="<?php echo $UserTitles->param_id;?>" <?php echo $selected;?>><?php echo $UserTitles->cValue;?></option>
                                                                <?php }?>
                                                            </select>
                                                            
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td width="12%">Surname <font color="#FF0000">*</font></td>
                                                        <td width="88%">
                                                        <?php echo form_input('lastname',set_value('lastname',$lastname_pick),'id="lastname" '.$readonly.' class="form-control input-sm" placeholder="Surname" style="width: 250px;"');?>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td>First Name <font color="#FF0000">*</font></td>
                                                        <td>
                                                        <?php echo form_input('firstname',set_value('firstname',$firstname_pick),'id="firstname" '.$readonly.' class="form-control input-sm" placeholder="First Name" style="width: 250px;"');?>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td>Middle Name</td>
                                                        <td>
                                                        <?php echo form_input('middlename',set_value('middlename',$middlename_pick),'id="middlename" '.$readonly.' class="form-control input-sm" placeholder="Middle Name" style="width: 250px;"');?>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td width="12%">Gender</td>
                                                        <td width="88%">
                                                            <select name="gender" id="gender" class="form-control input-sm" style="width: 100px;" <?php echo $readonly;?>>
                                                                <option value="">- Gender -</option>
                                                                <?php 
                                                                foreach($gender as $gender){
                                                                if($gender_pick == $gender->param_id){
                                                                    $selected = "selected='selected'";
                                                                }else{
                                                                    $selected = "";
                                                                }
                                                                ?>
                                                                <option value="<?php echo $gender->param_id;?>" <?php echo $selected;?>><?php echo $gender->cValue;?></option>
                                                                <?php }?>
                                                            </select>
                                                        </td>
                                                    </tr>
                                                    </table>
                                                    <fieldset>
                                                        <legend>
                                                            Appointment Details
                                                        </legend>

                                                        
                                                        <table cellpadding="3" cellspacing="3" width="100%">
                                                        <tr>
                                                            <td width="12%">Appointment Date <font color="#FF0000">*</font></td>
                                                            <td width="88%">
                                                            <?php echo form_input('dateAppointment',set_value('dateAppointment'),'id="dateAppointment" class="form-control input-sm" placeholder="Pick Date" style="width: 200px;"');?> 
                                                            </td>
                                                        </tr>

                                                        <tr>
                                                            <td width="12%">Appointment Time <font color="#FF0000">*</font></td>
                                                            <td width="88%">
                                                            <select name="appHour" id="appHour" class="form-control input-sm" style="width: 80px; display:inline-block;">
                                                                <option value="">HH</option>
                                                                <?php 
                                                                for($hh=1; $hh<=12; $hh++)
                                                                {
                                                                    if(strlen($hh) == 1)
                                                                    {
                                                                        $hh = "0".$hh;
                                                                    }
                                                                ?>
                                                                <option value="<?php echo $hh;?>"><?php echo $hh;?></option>
                                                                <?php }?>
                                                            </select>

                                                            <select name="appMinutes" id="appMinutes" class="form-control input-sm" style="width: 80px; display:inline-block;">
                                                                <option value="">MM</option>
                                                                <?php 
                                                                for($mm=0; $mm<60; $mm++)
                                                                {
                                                                    if(strlen($mm) == 1)
                                                                    {
                                                                        $mm = "0".$mm;
                                                                    }
                                                                ?>
                                                                <option value="<?php echo $mm;?>"><?php echo $mm;?></option>
                                                                <?php }?>
                                                            </select>

                                                            <select name="appAMPM" id="appAMPM" class="form-control input-sm" style="width: 80px; display:inline-block;">
                                                                <option value="">AM/PM</option>
                                                                <option value="AM">AM</option>
                                                                <option value="PM">PM</option>
                                                            </select>
                                                            </td>
                                                        </tr>

                                                        <tr>
                                                            <td width="12%">Consultant Doctor <font color="#FF0000">*</font></td>
                                                            <td width="88%">
                                                            <select name="consultantDoctor" id="consultantDoctor" class="form-control input-sm" style="width: 200px;">
                                                                <option value="">- Consultant Doctor -</option>
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
                                                            <td width="12%">Appointment Reason <font color="#FF0000">*</font></td>
                                                            <td width="88%">
                                                            <textarea name="appointmentReason" id="appointmentReason" placeholder="Appointment Reason" class="form-control input-sm" style="width: 500px;" rows="4"></textarea>
                                                            </td>
                                                        </tr>
                                                        </table>
                                                        
                                                    </fieldset>
                                        
                            </div>
                            <div class="box-footer clearfix">
                                <button class="btn btn-primary" name="btnSubmit2" type="submit"><i class="fa fa-save"></i> Save Appointment</button>
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
        
        <!-- BDAY -->
         <script src="<?php echo base_url();?>public/datepicker/js/jquery-1.9.1.min.js"></script>
        <script src="<?php echo base_url();?>public/datepicker/js/bootstrap-datepicker.js"></script>
        <script src="<?php echo base_url();?>public/timepicker/js/bootstrap-datepicker.js"></script>

        <script type="text/javascript">
            // When the document is ready
            $(document).ready(function () {
                
                $('#dateAppointment').datepicker({
                    //format: "dd/mm/yyyy"
					format: "yyyy-mm-dd"
                });  

                 $(".timepicker").timepicker({
                      showInputs: false
                    });
            
            });
        </script>
             <script type="text/javascript">
                  //Timepicker
                 
                 </script>
        <!-- END BDAY -->
        <script type="text/javascript">
        $(document).ready(function(){
            $('input, select, textarea').on('input change', function(){
                $(this).removeClass('field-error');
            });
        });
        </script>
        
    </body>
</html>