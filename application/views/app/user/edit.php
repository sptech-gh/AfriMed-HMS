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
        <link href="<?php echo base_url();?>public/datepicker/css/datepicker.css" rel="stylesheet" type="text/css" />
        
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
                    <h1>Edit User</h1>
                    <ol class="breadcrumb">
                        <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                        <li><a href="#">Admin</a></li>
                        <li><a href="<?php echo base_url()?>app/user">System User</a></li>
                        <li class="active">Edit User</li>
                    </ol>
                </section>

                <!-- Main content -->
                <section class="content">
                 
                 
                 <div class="row">
                 	<div class="col-md-12">
                     
                    	 <div class="box">
                            
                        	<div class="box-body table-responsive">

                            <div class="nav-tabs-custom">
                                <ul class="nav nav-tabs">
                                    <li class="active"><a href="#general_information" data-toggle="tab">General Information</a></li>
                                    <li><a href="#changePAssword" data-toggle="tab">Change Password</a></li>
                                </ul>
                                <div class="tab-content">
                                    <div class="tab-pane active" id="general_information">
											<?php
												$user_id = isset($user) && is_object($user) && isset($user->user_id) ? $user->user_id : '';
											?>


                                                    <form role="form" method="post" action="<?php echo base_url()?>app/user/edit/<?php echo $user_id?>" onSubmit="return confirm('Are you sure you want to save?');">
                                                    <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">   
                                                    <table cellpadding="3" cellspacing="3" width="100%">
                                                    <tr>
                                                        <td colspan="2">Required fields = <font color="#FF0000">*</font></td>
                                                    </tr>
                                                    <tR>
                                                        <td colspan="2">
                                                        <?php echo validation_errors(); ?>    
                                                        </td>
                                                    </tR>
                                                    <tr>
                                                        <td width="12%">User ID</td>
														<td width="88%"><input class="form-control input-sm" name="userid" id="userid" type="text" style="width: 100px;" required readonly value="<?php echo $user_id;?>"></td>
                                                    </tr>
                                                    <tr>
                                                        <td width="12%">Title</td>
                                                        <td width="88%">
                                                            <select name="title" id="title" class="form-control input-sm" style="width: 100px;">
                                                                <option value="">- Title -</option>
                                                                <?php 
                                                                foreach($UserTitles as $UserTitles){
                                                                if(isset($_POST['title']) && $_POST['title'] == $UserTitles->param_id || $user->title == $UserTitles->param_id){
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
                                                        <td width="12%">Last Name</td>
                                                        <td width="88%">
                                                        <?php echo form_input('lastname',set_value('lastname',$user->lastname),'id="lastname" class="form-control input-sm" placeholder="Last Name" style="width: 250px;"');?>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td>First Name</td>
                                                        <td>
                                                        <?php echo form_input('firstname',set_value('firstname',$user->firstname),'id="firstname" class="form-control input-sm" placeholder="First Name" style="width: 250px;"');?>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td>Middle Name</td>
                                                        <td>
                                                        <?php echo form_input('middlename',set_value('middlename',$user->middlename),'id="middlename" class="form-control input-sm" placeholder="Middle Name" style="width: 250px;"');?>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td>Birthday</td>
                                                        <td>
                                                        <?php echo form_input('birthday',set_value('birthday',$user->birthday),'id="birthday" class="form-control input-sm" placeholder="Birthday" style="width: 150px;"');?> 
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td>Birth Place</td>
                                                        <td>
                                                        <?php echo form_input('birthplace',set_value('birthplace',$user->birthplace),'id="birthplace" class="form-control input-sm" placeholder="Birth Place" style="width: 380px;"');?>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td width="12%">Gender</td>
                                                        <td width="88%">
                                                            <select name="gender" id="gender" class="form-control input-sm" style="width: 100px;">
                                                                <option value="">- Gender -</option>
                                                                <?php 
                                                                foreach($gender as $gender){
                                                                if(isset($_POST['gender']) && $_POST['gender'] == $gender->param_id || $user->gender == $gender->param_id){
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
                                                                if(isset($_POST['civil_status']) && $_POST['civil_status'] == $civilStatus->param_id || $user->civil_status == $civilStatus->param_id){
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
                                                        <td width="12%">Department</td>
                                                        <td width="88%">
                                                            <select name="department" id="department" class="form-control input-sm" style="width: 200px;">
                                                                <option value="">- Department -</option>
                                                                <?php 
                                                                foreach($departmentList as $departmentList){
                                                                if(isset($_POST['department']) && $_POST['department'] == $departmentList->department_id || $user->department == $departmentList->department_id){
                                                                    $selected = "selected='selected'";
                                                                }else{
                                                                    $selected = "";
                                                                }
                                                                ?>
                                                                <option value="<?php echo $departmentList->department_id;?>" <?php echo $selected;?>><?php echo $departmentList->dept_name;?></option>
                                                                <?php }?>
                                                            </select>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td width="12%">Designation</td>
                                                        <td width="88%">
                                                            <select name="designation" id="designation" class="form-control input-sm" style="width: 200px;">
                                                                <option value="">- Designation -</option>
                                                                <?php 
                                                                foreach($designationList as $designationList){
                                                                if(isset($_POST['designation']) && $_POST['designation'] == $designationList->designation_id || $user->designation == $designationList->designation_id){
                                                                    $selected = "selected='selected'";
                                                                }else{
                                                                    $selected = "";
                                                                }
                                                                ?>
                                                                <option value="<?php echo $designationList->designation_id;?>" <?php echo $selected;?>><?php echo $designationList->designation;?></option>
                                                                <?php }?>
                                                            </select>
                                                        </td>
                                                    </tr>
                                                    
                                                    <tr>
                                                        <td width="12%">User Role</td>
                                                        <td width="88%">
                                                            <select name="user_role" id="user_role" class="form-control input-sm" style="width: 200px;">
                                                                <option value="">- User Role -</option>
                                                                <?php 
                                                                foreach($userRoleList as $userRoleList){
                                                                if(isset($_POST['user_role']) && $_POST['user_role'] == $userRoleList->role_id || $user->user_role == $userRoleList->role_id){
                                                                    $selected = "selected='selected'";
                                                                }else{
                                                                    $selected = "";
                                                                }
                                                                ?>
                                                                <option value="<?php echo $userRoleList->role_id;?>" <?php echo $selected;?>><?php echo $userRoleList->role_name;?></option>
                                                                <?php }?>
                                                            </select>
                                                        </td>
                                                    </tr> <input type="hidden" name="cType">
                                                    <tr>
                                                        <td colspan="2"><h3>Contact Information</h3></td>
                                                    </tr>
                                                    <tr>
                                                        <td colspan="2"></td>
                                                    </tr>
                                                    <tr>
                                                        <td width="14%">No. of House</td>
                                                        <td width="86%">
                                                        <?php echo form_input('noofhouse',set_value('noofhouse',$user->street),'id="noofhouse" class="form-control input-sm" placeholder="No. of House" style="width: 250px;"');?>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td width="14%">Brgy./Subd.</td>
                                                        <td width="86%"> 
                                                        <?php echo form_input('brgy',set_value('brgy',$user->subd_brgy),'id="brgy" class="form-control input-sm" placeholder="Brgy./Subd." style="width: 250px;"');?>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td width="14%">City/Province</td>
                                                        <td width="86%"> 
                                                        <?php echo form_input('province',set_value('province',$user->province),'id="province" class="form-control input-sm" placeholder="City/Province" style="width: 250px;"');?>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td width="14%">Mobile No.</td>
                                                        <td width="86%"> 
                                                        <?php echo form_input('mobile',set_value('mobile',$user->mobile_no),'id="mobile" class="form-control input-sm" placeholder="Mobile No" style="width: 250px;"');?>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td width="14%">Phone No. <font color="#FF0000">*</font></td>
                                                        <td width="86%">
                                                        <input type="text" name="phone" id="phone" value="<?php echo set_value('phone',$user->phone_no); ?>" class="form-control input-sm" placeholder="Phone No." style="width: 250px;" required>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td width="14%">Email Address <font color="#FF0000">*</font></td>
                                                        <td width="86%"> 
                                                        <input type="text" name="email" id="email" value="<?php echo set_value('email',$user->email_address); ?>" class="form-control input-sm" placeholder="Email Address" style="width: 250px;" required>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td width="14%">Username <font color="#FF0000">*</font></td>
                                                        <td width="86%"> 
                                                       
                                                        <input type="text" name="username" id="username" value="<?php echo set_value('username',$user->username); ?>" class="form-control input-sm" placeholder="Username" style="width: 250px;" required>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td colspan="2">
                                                                <a href="<?php echo base_url();?>app/user" class="btn btn-default">Cancel</a>
                                                                 <button class="btn btn-primary" name="btnSubmit" id="btnSubmit" type="submit"><i class="fa fa-save"></i> Save</button>
                                                        </td>
                                                    </tr>
                                                    </table>
                                                    </form>
                                                    <!-- <iframe width="100%" frameborder="0" height="400" src="<?php echo base_url()?>app/user/upload_picture/<?php echo $user->user_id?>"></iframe> -->
                                                    
                                                
                                    </div>
                                    <div class="tab-pane" id="changePAssword">
                                        <form role="form" method="post" name="frmchangepassword" id="frmchangepassword">
                                        <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">   
                                        <div id="msgConfirm"></div>
													<input name="userid" id="userid" type="hidden" value="<?php echo $user_id;?>">
                                            <table cellpadding="3" cellspacing="3" width="100%">
                                            <tr>
                                                <td colspan="2">Required fields = <font color="#FF0000">*</font></td>
                                            </tr>
                                            <tr>
                                                <td width="12%">New Password <font color="#FF0000">*</font></td>
                                                <td width="88%"><input class="form-control input-sm" name="newpassword" id="newpassword" type="password" style="width: 200px;" required></td>
                                            </tr>
                                            <tr>
                                                <td width="12%">Re-type Password <font color="#FF0000">*</font></td>
                                                <td width="88%"><input class="form-control input-sm" name="retypepassword" id="retypepassword" type="password" style="width: 200px;" required></td>
                                            </tr>
                                            <tr>
                                                <td colspan="2">
                                                    <button class="btn btn-primary" name="btnchangepassword" id="btnchangepassword" type="submit">Change Password</button>
                                                </td>
                                            </tr>
                                            </table>
                                        </form>
                                    </div>
                                </div>
                            </div>  
                                		
                                        
                                        
                                        
                               
                                
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
                
                $('#birthday').datepicker({
                    //format: "dd/mm/yyyy"
					format: "yyyy-mm-dd"
                });  

                $('#frmchangepassword').on('submit', function(e){

                    e.preventDefault();
                    
                    if(confirm('Are you sure you want to change password?'))
                    {
                        var formdata = $(this).serialize();

                        $.ajax({
                            url: "<?php echo base_url()?>app/user/changepassword",
                            type: "POST",
                            data: formdata,
                            dataType: "json",
                            success: function(data){

                                if(data.status == 0)
                                {
                                    $('#msgConfirm').html("<div class='alert alert-success'>" + data.message + "</div>");
                                }
                                else
                                {
                                    $('#msgConfirm').html("<div class='alert alert-danger'>" + data.message + "</div>");
                                }

                                $('#btnchangepassword').prop('disabled',false);
                                $('#btnchangepassword').text('Change Password');
                            }, beforeSend: function(){
                                $('#btnchangepassword').prop('disabled',true);
                                $('#btnchangepassword').text('Saving...');
                            }
                        });
                    }

                });
            
            });
        </script>
        <!-- END BDAY -->
        
    </body>
</html>