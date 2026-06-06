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
                 .tab-has-error > a { color: #dd4b39 !important; font-weight: bold !important; }
                 .tab-has-error > a:after { content: ' \26A0'; }
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
                    var allTabs = document.querySelectorAll('.nav-tabs > li');
                    for (var i = 0; i < allTabs.length; i++) allTabs[i].classList.remove('tab-has-error');

                    var errors = [];
                    var firstErrorTab = null;

                    // Tab 1: Personal Information
                    var requiredTab1 = [
                        {id: 'title', label: 'Title'},
                        {id: 'lastname', label: 'Surname'},
                        {id: 'firstname', label: 'First Name'},
                        {id: 'birthday', label: 'Birthday'},
                    ];
                    for (var i = 0; i < requiredTab1.length; i++) {
                        var el = document.getElementById(requiredTab1[i].id);
                        if (el && !el.value.trim()) {
                            errors.push(requiredTab1[i].label + ' is required');
                            el.classList.add('field-error');
                            if (!firstErrorTab) firstErrorTab = 'tab_1';
                        }
                    }

                    if (errors.length > 0) {
                        var tabs = document.querySelectorAll('.tab-pane');
                        for (var t = 0; t < tabs.length; t++) {
                            if (tabs[t].querySelector('.field-error')) {
                                var li = document.querySelectorAll('.nav-tabs > li');
                                if (li[t]) li[t].classList.add('tab-has-error');
                            }
                        }
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
                        if (firstErrorTab) {
                            var tabMap = {'tab_1': 0, 'tab_2': 1, 'tab_3': 2};
                            var idx = tabMap[firstErrorTab];
                            var tabLinks = document.querySelectorAll('.nav-tabs > li > a');
                            if (tabLinks[idx]) tabLinks[idx].click();
                        }
                        window.scrollTo(0, 0);
                        return false;
                    }

                    return confirm('Are you sure you want to save this patient record?');
                 }
                 </script>
                 <div class="row">
                 	<div class="col-md-12">
                    <form role="form" method="post" action="<?php echo base_url()?>app/patient/save" onSubmit="return validate()">    
                    <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                    	 <div class="box">
                         		
                         		 <div class="box-footer clearfix">
                            	
                                            <a href="<?php echo base_url();?>app/patient" class="btn btn-default">Cancel</a>
                                            <button class="btn btn-primary" name="btnSubmit" id="btnSubmit" type="submit"><i class="fa fa-save"></i> Save Patient</button>
                                 
                            </div>
                            <div id="validation-msg"></div>
                            
                        	<div class="box-body table-responsive">
                            	
                                
                                		<div class="nav-tabs-custom">
                                        	<ul class="nav nav-tabs">
                                				<li class="active"><a href="#tab_1" data-toggle="tab">Personal Information</a></li>
                                    			<li><a href="#tab_2" data-toggle="tab">Contact Information</a></li>
                                                <li><a href="#tab_3" data-toggle="tab">Other Information</a></li>
                                			</ul>
                                            <div class="tab-content">
                                            	<div class="tab-pane active" id="tab_1">
                                                	<table cellpadding="3" cellspacing="3" width="100%">
                                                    <tr>
                                                    	<td colspan="2">
                                                        <span class="required-note"><font color="#FF0000">*</font> indicates a required field</span>
                                                        <?php echo validation_errors(); ?>
                                                        </td>
                                                    </tr>
                                                    <?php
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
													?>
                                                    <input type="hidden" name="userID2" value="<?php echo $userID2;?>">
                                                    <tr>
                                                    	<td width="12%">Patient ID</td>
                                                        <td width="88%"><input class="form-control input-sm" name="patientID" id="patientID" type="text" style="width: 100px;" required readonly value="<?php echo $userID;?>"></td>
                                                    </tr>
                                                    <tr>
                                                    	<td width="12%">Title <font color="#FF0000">*</font></td>
                                                        <td width="88%">
                                                        	<select name="title" id="title" class="form-control input-sm" style="width: 100px;">
                                                            	<option value="">- Title -</option>
																<?php 
																foreach($UserTitles as $UserTitles){
																if(isset($_POST['title']) && $_POST['title'] == $UserTitles->param_id){
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
                                                        <?php echo form_input('lastname',set_value('lastname'),'id="lastname" class="form-control input-sm" placeholder="Surname" style="width: 250px;"');?>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                    	<td>First Name <font color="#FF0000">*</font></td>
                                                        <td>
                                                        <?php echo form_input('firstname',set_value('firstname'),'id="firstname" class="form-control input-sm" placeholder="First Name" style="width: 250px;"');?>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                    	<td>Middle Name</td>
                                                        <td>
                                                        <?php echo form_input('middlename',set_value('middlename'),'id="middlename" class="form-control input-sm" placeholder="Middle Name" style="width: 250px;"');?>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                    	<td>Birthday <font color="#FF0000">*</font></td>
                                                        <td>
                                                        <?php echo form_input('birthday',set_value('birthday'),'id="birthday" class="form-control input-sm" placeholder="YYYY-MM-DD" style="width: 150px;"');?> 
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                    	<td>Birth Place</td>
                                                        <td>
                                                        <?php echo form_input('birthplace',set_value('birthplace'),'id="birthplace" class="form-control input-sm" placeholder="Birth Place" style="width: 380px;"');?>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                    	<td width="12%">Gender</td>
                                                        <td width="88%">
                                                        	<select name="gender" id="gender" class="form-control input-sm" style="width: 100px;">
                                                            	<option value="">- Gender -</option>
                                                                <?php 
																foreach($gender as $gender){
																if(isset($_POST['gender']) && $_POST['gender'] == $gender->param_id){
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
                                                    <tr>
                                                    	<td width="12%">Civil Status</td>
                                                        <td width="88%">
                                                        	<select name="civil_status" id="civil_status" class="form-control input-sm" style="width: 140px;">
                                                            	<option value="">- Civil Status -</option>
                                                                <?php 
																foreach($civilStatus as $civilStatus){
																if(isset($_POST['civil_status']) && $_POST['civil_status'] == $civilStatus->param_id){
																	$selected = "selected='selected'";
																}else{
																	$selected = "";
																}
																?>
                                                            	<option value="<?php echo $civilStatus->param_id;?>" <?php echo $selected;?>><?php echo $civilStatus->cValue;?></option>
                                                                <?php }?>
                                                            </select>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                    	<td width="12%">Religion</td>
                                                        <td width="88%">
                                                        	<select name="religion" id="religion" class="form-control input-sm" style="width: 140px;">
                                                            	<option value="">- Religion -</option>
                                                                <?php 
																foreach($religionList as $religion){
																if(isset($_POST['religion']) && $_POST['religion'] == $religion->param_id){
																	$selected = "selected='selected'";
																}else{
																	$selected = "";
																}
																?>
                                                            	<option value="<?php echo $religion->param_id;?>" <?php echo $selected;?>><?php echo $religion->cValue;?></option>
                                                                <?php }?>
                                                            </select>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                    	<td width="12%">Blood Group </td>
                                                        <td width="88%">
                                                        	<select name="bloodGroup" id="bloodGroup" class="form-control input-sm" style="width: 125px;">
                                                            	<option value="">- Blood Group -</option>
                                                            	<?php 
																foreach($bloodGroup as $bloodGroup){
																if(isset($_POST['bloodGroup']) && $_POST['bloodGroup'] == $bloodGroup->param_id){
																	$selected = "selected='selected'";
																}else{
																	$selected = "";
																}
																?>
                                                            	<option value="<?php echo $bloodGroup->param_id;?>" <?php echo $selected;?>><?php echo $bloodGroup->cValue;?></option>
                                                                <?php }?>
                                                            </select>
                                                        </td>
                                                    </tr>
                                                    </table>
                                                </div>
                                                <div class="tab-pane" id="tab_2">
                                                	<table cellpadding="3" cellspacing="3" width="100%">
                                                    <tr>
                                                    	<td colspan="2"></td>
                                                    </tr>
                                                    <tr>
                                                    	<td width="14%">Address</td>
                                                        <td width="86%">
                                                        <?php echo form_input('noofhouse',set_value('noofhouse'),'id="noofhouse" class="form-control input-sm" placeholder="Address" style="width: 250px;"');?>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                    	<td width="14%">Address2</td>
                                                        <td width="86%">
                                                        <?php echo form_input('address2',set_value('address2'),'id="address2" class="form-control input-sm" placeholder="Address2" style="width: 250px;"');?>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                    	<td width="14%">City</td>
                                                        <td width="86%"> 
                                                        <?php echo form_input('province',set_value('province'),'id="province" class="form-control input-sm" placeholder="City" style="width: 250px;"');?>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                    	<td width="14%">Phone No (Office)</td>
                                                        <td width="86%">
                                                        <?php echo form_input('phone_office',set_value('phone_office'),'id="phone_office" class="form-control input-sm" placeholder="Phone No (Office)" style="width: 250px;"');?>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                    	<td width="14%">Phone No (Home)</td>
                                                        <td width="86%">
                                                        <?php echo form_input('phone',set_value('phone'),'id="phone" class="form-control input-sm" placeholder="Phone No (Home)" style="width: 250px;"');?>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                    	<td width="14%">Phone No (Mobile)</td>
                                                        <td width="86%"> 
                                                        <?php echo form_input('mobile',set_value('mobile'),'id="mobile" class="form-control input-sm" placeholder="Phone No (Mobile)" style="width: 250px;"');?>
                                                        </td>
                                                    </tr>
                                                    
                                                    <tr>
                                                    	<td width="14%">Email Address</td>
                                                        <td width="86%"> 
                                                        <?php echo form_input('email',set_value('email'),'id="email" class="form-control input-sm" placeholder="Email Address" style="width: 250px;"');?>
                                                        </td>
                                                    </tr>
                                                    </table>
                                                </div>
                                                <div class="tab-pane" id="tab_3">
                                               		<table cellpadding="3" cellspacing="3" width="100%">
                                                    <tr>
                                                    	<td colspan="2"></td>
                                                    </tr>
                                                    <tr>
                                                    	<td width="18%">Insurance Company</td>
                                                        <td width="82%">
                                                        <select name="insurance_comp" id="insurance_comp" class="form-control input-sm" style="width: 250px;">
                                                            	<option value="">- None -</option>
                                                            	<?php 
																foreach($insuranceCompList as $insuranceCompList){
																if(isset($_POST['insurance_comp']) && $_POST['insurance_comp'] == $insuranceCompList->in_com_id){
																	$selected = "selected='selected'";
																}else{
																	$selected = "";
																}
																?>
                                                            	<option value="<?php echo $insuranceCompList->in_com_id;?>" <?php echo $selected;?>><?php echo $insuranceCompList->company_name;?></option>
                                                                <?php }?>
                                                            </select>
                                                        
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                    	<td width="18%">Insurance ID Number</td>
                                                        <td width="82%">
                                                        <?php echo form_input('insurance_id',set_value('insurance_id'),'id="insurance_id" class="form-control input-sm" placeholder="Insurance ID Number" style="width: 250px;"');?>
                                                        </td>
                                                    </tr>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        
                                        
                               
                                
                            </div>
                            <div class="box-footer clearfix">
                                <a href="<?php echo base_url();?>app/patient" class="btn btn-default">Cancel</a>
                                <button class="btn btn-primary" name="btnSubmit2" type="submit"><i class="fa fa-save"></i> Save Patient</button>
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
        <script type="text/javascript">
            $(document).ready(function () {
                $('#birthday').datepicker({
					format: "yyyy-mm-dd",
					autoclose: true,
					todayHighlight: true
                });
                $('input, select, textarea').on('input change', function(){
                    $(this).removeClass('field-error');
                });
            });
        </script>
        <!-- END BDAY -->
        
    </body>
</html>