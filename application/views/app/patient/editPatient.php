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
                        {id: 'birthday', label: 'Birthday'}
                    ];
                    for (var i = 0; i < requiredTab1.length; i++) {
                        var el = document.getElementById(requiredTab1[i].id);
                        if (el && !el.value.trim()) {
                            errors.push(requiredTab1[i].label + ' is required');
                            el.classList.add('field-error');
                            if (!firstErrorTab) firstErrorTab = 0;
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
                        if (firstErrorTab !== null) {
                            var tabLinks = document.querySelectorAll('.nav-tabs > li > a');
                            if (tabLinks[firstErrorTab]) tabLinks[firstErrorTab].click();
                        }
                        window.scrollTo(0, 0);
                        return false;
                    }

                    return confirm('Are you sure you want to save changes?');
                 }
                 </script>
            <!-- Right side column. Contains the navbar and content of the page -->
            <aside class="right-side">                
                <!-- Content Header (Page header) -->
                <section class="content-header">
                    <h1>Modify Patient</h1>
                    <ol class="breadcrumb">
                        <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                        <li><a href="#">Patient Management</a></li>
                        <li class="active">Modify Patient</li>
                    </ol>
                </section>

                <!-- Main content -->
                <section class="content">
                 
                 
                 <div class="row">
                 	<div class="col-md-12">
                    <form role="form" method="post" action="<?php echo base_url()?>app/patient/edit" onSubmit="return validate()">    
                    	 <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                    	 <input type="hidden" name="id" value="<?php echo $patientInfo->patient_no;?>">
                         <div class="box">
                         		
                         		 <div class="box-footer clearfix">
                            	
                                            <a href="<?php echo base_url();?>app/patient" class="btn btn-default">Cancel</a>
                                            <button class="btn btn-primary" name="btnSubmit" id="btnSubmit" type="submit"><i class="fa fa-save"></i> Save Changes</button>
                                 
                            </div>
                            <div id="validation-msg"></div>
                            
                        	<div class="box-body table-responsive">
                            	
                                
                                		<div class="nav-tabs-custom">
                                        	<ul class="nav nav-tabs">
                                				<li class="active"><a href="#tab_1" data-toggle="tab">Personal Information</a></li>
                                    			<li><a href="#tab_2" data-toggle="tab">Contact Information</a></li>
                                                <li><a href="#tab_4" data-toggle="tab">Other Information</a></li>
                                                <li><a href="#tab_3" data-toggle="tab">Profile Picture</a></li>
                                                <li><a href="#tab_5" data-toggle="tab">Emergency Contact Information</a></li>
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
                                                    <tr>
                                                    	<td width="12%">Patient ID</td>
                                                        <td width="88%"><input class="form-control input-sm" name="patientID" id="patientID" type="text" style="width: 100px;" required readonly value="<?php echo $patientInfo->patient_no;?>"></td>
                                                    </tr>
                                                    <tr>
                                                    	<td width="12%">Title <font color="#FF0000">*</font></td>
                                                        <td width="88%">
                                                        	<select name="title" id="title" class="form-control input-sm" style="width: 100px;">
                                                            	<option value="">- Title -</option>
																<?php 
																foreach($UserTitles as $UserTitles){
																if(isset($_POST['title']) && $_POST['title'] == $UserTitles->param_id || $patientInfo->title == $UserTitles->param_id){
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
                                                        <?php echo form_input('lastname',set_value('lastname',$patientInfo->lastname),'id="lastname" class="form-control input-sm" placeholder="Surname" style="width: 250px;"');?>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                    	<td>First Name <font color="#FF0000">*</font></td>
                                                        <td>
                                                        <?php echo form_input('firstname',set_value('firstname',$patientInfo->firstname),'id="firstname" class="form-control input-sm" placeholder="First Name" style="width: 250px;"');?>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                    	<td>Middle Name</td>
                                                        <td>
                                                        <?php echo form_input('middlename',set_value('middlename',$patientInfo->middlename),'id="middlename" class="form-control input-sm" placeholder="Middle Name" style="width: 250px;" ');?>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                    	<td>Birthday <font color="#FF0000">*</font></td>
                                                        <td>
                                                        <input class="form-control input-sm" name="birthday" id="birthday" type="text" value="<?php echo $patientInfo->birthday?>" placeholder="YYYY-MM-DD" style="width:150px;">
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                    	<td>Birth Place</td>
                                                        <td>
                                                        <?php echo form_input('birthplace',set_value('birthplace',$patientInfo->birthplace),'id="birthplace" class="form-control input-sm" placeholder="Birth Place" style="width: 380px;"');?>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                    	<td width="12%">Gender</td>
                                                        <td width="88%">
                                                        	<select name="gender" id="gender" class="form-control input-sm" style="width: 100px;">
                                                            	<option value="">- Gender -</option>
                                                                <?php 
																foreach($gender as $gender){
																if(isset($_POST['gender']) && $_POST['gender'] == $gender->param_id || $patientInfo->gender == $gender->param_id){
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
																if(isset($_POST['civil_status']) && $_POST['civil_status'] == $civilStatus->param_id || $patientInfo->civil_status == $civilStatus->param_id){
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
																if(isset($_POST['religion']) && $_POST['religion'] == $religion->param_id || $patientInfo->religion == $religion->param_id){
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
                                                    	<td width="12%">Blood Group</td>
                                                        <td width="88%">
                                                        	<select name="bloodGroup" id="bloodGroup" class="form-control input-sm" style="width: 125px;" >
                                                            	<option value="">- Blood Group -</option>
                                                            	<?php 
																foreach($bloodGroup as $bloodGroup){
																if(isset($_POST['bloodGroup']) && $_POST['bloodGroup'] == $bloodGroup->param_id || $patientInfo->blood_group == $bloodGroup->param_id){
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
                                                    	<td width="14%">No. of House</td>
                                                        <td width="86%">
                                                        <?php echo form_input('noofhouse',set_value('noofhouse',$patientInfo->street),'id="noofhouse" class="form-control input-sm" placeholder="No. of House" style="width: 250px;"');?>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                    	<td width="14%">Brgy./Subd.</td>
                                                        <td width="86%"> 
                                                        <?php echo form_input('brgy',set_value('brgy',$patientInfo->subd_brgy),'id="brgy" class="form-control input-sm" placeholder="Brgy./Subd." style="width: 250px;"');?>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                    	<td width="14%">City/Province</td>
                                                        <td width="86%"> 
                                                        <?php echo form_input('province',set_value('province',$patientInfo->province),'id="province" class="form-control input-sm" placeholder="City/Province" style="width: 250px;"');?>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                    	<td width="14%">Phone No (Office)</td>
                                                        <td width="86%">
                                                        <?php echo form_input('phone_office',set_value('phone_office',$patientInfo->phone_no_office),'id="phone_office" class="form-control input-sm" placeholder="Phone No (Office)" style="width: 250px;"');?>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                    	<td width="14%">Phone No. (Home)</td>
                                                        <td width="86%">
                                                        <?php echo form_input('phone',set_value('phone',$patientInfo->phone_no),'id="phone" class="form-control input-sm" placeholder="Phone No (Home)" style="width: 250px;"');?>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                    	<td width="14%">Phone No (Mobile)</td>
                                                        <td width="86%"> 
                                                        <?php echo form_input('mobile',set_value('mobile',$patientInfo->mobile_no),'id="mobile" class="form-control input-sm" placeholder="Phone No (Mobile)" style="width: 250px;"');?>
                                                        </td>
                                                    </tr>
                                                    
                                                    <tr>
                                                    	<td width="14%">Email Address</td>
                                                        <td width="86%"> 
                                                        <?php echo form_input('email',set_value('email',$patientInfo->email_address),'id="email" class="form-control input-sm" placeholder="Email Address" style="width: 250px;"');?>
                                                        </td>
                                                    </tr>
                                                    </table>
                                                </div>
                                                
                                                <div class="tab-pane" id="tab_4">
                                               		<table cellpadding="3" cellspacing="3" width="100%">
                                                    <tr>
                                                    	<td colspan="2"></td>
                                                    </tr>
                                                    <?php
                                                        $nhis_num = isset($patientInfo->nhis_number) ? $patientInfo->nhis_number : '';
                                                        $nhis_exp = isset($patientInfo->nhis_expiry_date) ? $patientInfo->nhis_expiry_date : '';
                                                        $nhis_st  = isset($patientInfo->nhis_status) ? strtoupper(trim((string)$patientInfo->nhis_status)) : '';
                                                        $nhis_badge = '';
                                                        if ($nhis_num != '') {
                                                            if ($nhis_st === 'ACTIVE') {
                                                                $nhis_badge = '<span class="label label-success"><i class="fa fa-check-circle"></i> NHIS Active</span>';
                                                            } elseif ($nhis_st === 'EXPIRED') {
                                                                $nhis_badge = '<span class="label label-danger"><i class="fa fa-exclamation-triangle"></i> NHIS Expired</span>';
                                                            } elseif ($nhis_st === 'INVALID') {
                                                                $nhis_badge = '<span class="label label-warning"><i class="fa fa-ban"></i> NHIS Invalid</span>';
                                                            } else {
                                                                $nhis_badge = '<span class="label label-default">NHIS Unknown</span>';
                                                            }
                                                        }
                                                    ?>
                                                    <?php if ($nhis_num != ''): ?>
                                                    <tr>
                                                        <td width="18%"><strong>NHIS Status</strong></td>
                                                        <td width="82%"><?php echo $nhis_badge; ?>
                                                            <?php if ($nhis_st === 'EXPIRED'): ?>
                                                            <span style="color:#c9302c;margin-left:8px;"><i class="fa fa-warning"></i> Card expired — billing defaults to CASH</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                    <?php endif; ?>
                                                    <tr>
                                                    	<td width="18%"><strong>NHIS Number</strong></td>
                                                        <td width="82%">
                                                        <div class="input-group" style="width:380px;">
                                                        <?php echo form_input('nhis_number',set_value('nhis_number',$nhis_num),'id="nhis_number" class="form-control input-sm" placeholder="NHIS Membership Number"');?>
                                                        <span class="input-group-btn">
                                                            <button type="button" id="btn_nhis_verify" class="btn btn-info btn-sm"><i class="fa fa-check-circle"></i> Verify</button>
                                                        </span>
                                                        </div>
                                                        <div id="nhis_verify_result" style="margin-top:5px;display:none;"></div>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                    	<td width="18%"><strong>NHIS Expiry Date</strong></td>
                                                        <td width="82%">
                                                        <input class="form-control input-sm" name="nhis_expiry_date" id="nhis_expiry_date" type="text" value="<?php echo set_value('nhis_expiry_date', ($nhis_exp && $nhis_exp !== '0000-00-00') ? $nhis_exp : ''); ?>" placeholder="YYYY-MM-DD" style="width: 170px;">
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                    	<td colspan="2"><hr style="margin:5px 0;border-top:1px dashed #ccc;"></td>
                                                    </tr>
                                                    <tr>
                                                    	<td width="18%">Insurance Company</td>
                                                        <td width="82%">
                                                        <select name="insurance_comp" id="insurance_comp" class="form-control input-sm" style="width: 250px;">
                                                            	<option value="">- None -</option>
                                                            	<?php 
																foreach($insuranceCompList as $insuranceCompList){
																if(isset($_POST['insurance_comp']) && $_POST['insurance_comp'] == $insuranceCompList->in_com_id || $patientInfo->Insurance_comp == $insuranceCompList->in_com_id){
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
                                                        <?php echo form_input('insurance_id',set_value('insurance_id',$patientInfo->insurance_no),'id="insurance_id" class="form-control input-sm" placeholder="Insurance ID Number" style="width: 250px;"');?>
                                                        </td>
                                                    </tr>
                                                    </table>
                                                </div>
                                                
                                                <div class="tab-pane" id="tab_3">
                                                
                                                	<iframe width="100%" frameborder="0" height="400" src="<?php echo base_url()?>app/patient/upload_picture/<?php echo $patientInfo->patient_no?>"></iframe>
                                                </div>

                                                <div class="tab-pane" id="tab_5">
                                               		<table cellpadding="3" cellspacing="3" width="100%">
                                                    <tr>
                                                    	<td colspan="2"></td>
                                                    </tr>
                                                    
                                                    <tr>
                                                    	<td width="18%">Fullname</td>
                                                        <td width="82%">
                                                        <?php echo form_input('emergency_fullname',set_value('emergency_fullname',$patientInfo->emergency_fullname),'id="insurance_id" class="form-control input-sm" placeholder="Emergency Contact\'s Fullname" style="width: 250px;"');?>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                    	<td width="18%">Phone Number</td>
                                                        <td width="82%">
                                                        <?php echo form_input('emergency_phone_number',set_value('emergency_phone_number',$patientInfo->emergency_phone_number),'id="emergency_phone_number" class="form-control input-sm" placeholder="Phone Number" style="width: 250px;"');?>
                                                        </td>
                                                    </tr>
                                                    
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        
                                        
                               
                                
                            </div>
                            <div class="box-footer clearfix">
                                <a href="<?php echo base_url();?>app/patient" class="btn btn-default">Cancel</a>
                                <button class="btn btn-primary" name="btnSubmit2" type="submit"><i class="fa fa-save"></i> Save Changes</button>
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
            // When the document is ready
            $(document).ready(function () {
                
                $('#birthday').datepicker({
                    //format: "dd/mm/yyyy"
                  format: "yyyy-mm-dd"
                });

                $('#nhis_expiry_date').datepicker({
                    format: "yyyy-mm-dd",
                    autoclose: true,
                    todayHighlight: true
                });
            
            });
        </script>
        <!-- END BDAY -->
        <script type="text/javascript">
        $(document).ready(function(){
            $('input, select, textarea').on('input change', function(){
                $(this).removeClass('field-error');
            });
        });
        </script>
        <script type="text/javascript">
        var csrfName = '<?php echo $this->security->get_csrf_token_name(); ?>';
        var csrfHash = '<?php echo $this->security->get_csrf_hash(); ?>';
        $(document).ready(function(){
            $('#btn_nhis_verify').on('click', function(){
                var nhis = $.trim($('#nhis_number').val()).toUpperCase();
                var $res = $('#nhis_verify_result');
                if (!nhis){
                    $res.html('<span class="label label-warning"><i class="fa fa-exclamation-triangle"></i> Enter an NHIS number first.</span>').show();
                    return;
                }
                var $btn = $(this);
                $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Verifying...');
                $res.html('').hide();
                var verifyData = {nhis_number: nhis, patient_no: '<?php echo isset($patientInfo->patient_no) ? $patientInfo->patient_no : ''; ?>'};
                verifyData[csrfName] = csrfHash;
                $.ajax({
                    url: '<?php echo base_url(); ?>app/patient/nhis_verify_ajax',
                    type: 'POST',
                    data: verifyData,
                    dataType: 'json',
                    success: function(r){
                        if (r && r.success){
                            var exp = r.expiry_date || '';
                            if (exp){ $('#nhis_expiry_date').val(exp); }
                            $res.html(
                                '<span class="label label-success" style="font-size:12px;padding:5px 10px;">'
                                + '<i class="fa fa-check-circle"></i> ACTIVE &nbsp;|&nbsp; '
                                + (r.name ? r.name + ' &nbsp;|&nbsp; ' : '')
                                + 'Expires: ' + (exp || 'N/A')
                                + (r.scheme ? ' &nbsp;|&nbsp; Scheme: ' + r.scheme : '')
                                + '</span>'
                            ).show();
                        } else {
                            var msg = (r && r.message) ? r.message : 'Verification failed.';
                            $res.html('<span class="label label-danger" style="font-size:12px;padding:5px 10px;"><i class="fa fa-times-circle"></i> ' + msg + '</span>').show();
                        }
                    },
                    error: function(){
                        $res.html('<span class="label label-danger"><i class="fa fa-times-circle"></i> Server error. Try again.</span>').show();
                    },
                    complete: function(){
                        $btn.prop('disabled', false).html('<i class="fa fa-check-circle"></i> Verify');
                    }
                });
            });
        });
        </script>

        <script>
        (function() {
            var skipIds = ['birthday','nhis_number','nhis_expiry','email','patientID'];
            var skipTypes = ['date','email','number','tel','hidden','submit','button','checkbox','radio','password'];
            function toTitleCase(str) {
                return str.replace(/\w\S*/g, function(w) {
                    return w.charAt(0).toUpperCase() + w.slice(1);
                });
            }
            function applyCapitalize(el) {
                if (!el || el.tagName !== 'INPUT' && el.tagName !== 'TEXTAREA') return;
                if (skipTypes.indexOf((el.type || '').toLowerCase()) !== -1) return;
                if (skipIds.indexOf(el.id) !== -1) return;
                el.addEventListener('input', function() {
                    var pos = this.selectionStart;
                    this.value = toTitleCase(this.value);
                    this.setSelectionRange(pos, pos);
                });
            }
            document.addEventListener('DOMContentLoaded', function() {
                var fields = document.querySelectorAll('input[type="text"], textarea');
                for (var i = 0; i < fields.length; i++) applyCapitalize(fields[i]);
            });
        })();
        </script>
        
    </body>
</html>