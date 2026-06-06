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
                    <h1>Edit Role</h1>
                    <ol class="breadcrumb">
                        <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                        <li><a href="#">User Management</a></li>
                        <li><a href="<?php echo base_url()?>app/roles">User Roles</a></li>
                        <li class="active">Edit Role</li>
                    </ol>
                </section>

                <!-- Main content -->
                <section class="content">
                 
                 
                 <div class="row">
                  	<div class="col-md-12">
                    	<?php
						$role_id = isset($roles) && is_object($roles) && isset($roles->role_id) ? $roles->role_id : '';
						$role_name = isset($roles) && is_object($roles) && isset($roles->role_name) ? $roles->role_name : '';
						$role_description = isset($roles) && is_object($roles) && isset($roles->role_description) ? $roles->role_description : '';
						$role_module = isset($roles) && is_object($roles) && isset($roles->module) ? $roles->module : '';
					?>
                    
                    	 <div class="box">
                          		
                          		<div class="box-header">
                                    <h3 class="box-title"></h3>
                                    
                                </div>
                        	<div class="box-body table-responsive">
	                            	<form role="form" method="post" action="<?php echo base_url()?>app/roles/edit/<?php echo $role_id?>" onSubmit="return confirm('Are you sure you want to save?');">
								<input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
								<input type="hidden" name="id" id="id" value="<?php echo $role_id?>">
                                 		<?php echo validation_errors(); ?>   
                                        
                                        <!--<div class="form-group">
                                            <label for="exampleInputEmail1">Access Module</label>
                                            <select name="module" id="module" class="form-control input-sm" style="width: 350px;" required>
                                            	<option value=""> - Access Module - </option>
                                            	<option value="administrator" <?php if($roles->module == "administrator"){ echo "selected";}?>>Administrator Module</option>
                                                <option value="doctor" <?php if($roles->module == "doctor"){ echo "selected";}?>>Doctor Module</option>
                                                <option value="helpdesk" <?php if($roles->module == "helpdesk"){ echo "selected";}?>>Help Desk Module</option>
                                                <option value="nursing" <?php if($roles->module == "nursing"){ echo "selected";}?>>Nursing Module</option>
                                                <option value="billing" <?php if($roles->module == "billing"){ echo "selected";}?>>Billing Module</option>
                                            </select>
                                        </div>-->
									<?php
										$current_module = isset($_POST['module']) ? (string)$_POST['module'] : (string)$role_module;
									?>
									<div class="form-group">
										<label for="module">Module</label>
										<select name="module" id="module" class="form-control input-sm" style="width: 350px;">
											<option value="">- Module -</option>
											<option value="administrator" <?php echo ($current_module === 'administrator') ? "selected='selected'" : ""; ?>>Administrator</option>
											<option value="doctor" <?php echo ($current_module === 'doctor') ? "selected='selected'" : ""; ?>>Doctor</option>
											<option value="nurse" <?php echo ($current_module === 'nurse') ? "selected='selected'" : ""; ?>>Nurse</option>
											<option value="pharmacy" <?php echo ($current_module === 'pharmacy') ? "selected='selected'" : ""; ?>>Pharmacy</option>
											<option value="laboratory" <?php echo ($current_module === 'laboratory') ? "selected='selected'" : ""; ?>>Laboratory</option>
											<option value="sonography" <?php echo ($current_module === 'sonography') ? "selected='selected'" : ""; ?>>Sonography</option>
											<option value="sonographer" <?php echo ($current_module === 'sonographer') ? "selected='selected'" : ""; ?>>Sonographer</option>
											<option value="billing" <?php echo ($current_module === 'billing') ? "selected='selected'" : ""; ?>>Billing</option>
										</select>
									</div>
                                
                                		<div class="form-group">
                                            <label for="exampleInputEmail1">Role Name</label>
									<input class="form-control input-sm" name="role_name" id="role_name" type="text" placeholder="Role Name" style="width: 350px;" required value="<?php echo $role_name?>">
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="exampleInputEmail1">Role Description</label>
									<input class="form-control input-sm" name="role_description" id="role_description" type="text" placeholder="Role Description" style="width: 350px;" required value="<?php echo $role_description?>">
                                        </div>
                                        
                                        <div class="form-group">
                                            <a href="<?php echo base_url();?>app/roles" class="btn btn-default">Cancel</a>
                                            <button class="btn btn-primary" name="btnSubmit" id="btnSubmit" type="submit"><i class="fa fa-save"></i> Save</button>
                                        </div>
                                        
                                </form>
                                
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
        
    </body>
</html>