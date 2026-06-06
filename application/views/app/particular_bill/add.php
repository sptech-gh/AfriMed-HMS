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
                    <h1>Add Particular Bill</h1>
                    <ol class="breadcrumb">
                        <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                        <li><a href="#">Admin</a></li>
                        <li><a href="<?php echo base_url()?>app/particular_bill">Particular Bill</a></li>
                        <li class="active">Add Particular Bill</li>
                    </ol>
                </section>
				
                <!-- Main content -->
                <section class="content">
                 
                 
                 <div class="row">
                 	<div class="col-md-12">
                    
                    	 <div class="box">
                         		
                         		<div class="box-header">
                                    <h3 class="box-title"></h3>
                                    
                                </div>
                        	<div class="box-body table-responsive">
                            	<form role="form" method="post" action="<?php echo base_url()?>app/particular_bill/save" onSubmit="return confirm('Are you sure you want to save?');">
                                <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                                		<?php echo validation_errors(); ?>   
                                
                                		<div class="form-group">
                                            <label for="exampleInputEmail1">Group Name</label>
                                            <select name="group_name" class="form-control input-sm" required style="width: 350px;">
                                            	<option value="">- Group Name -</option>
                                            	<?php 
                                            $posted_group = set_value('group_name');
                                            if (!empty($group_name) && is_array($group_name)):
                                            		foreach($group_name as $grp):
                                            			$selected = ($posted_group == $grp->group_id) ? 'selected' : '';
                                            ?>
                                                <option value="<?php echo isset($grp->group_id) ? $grp->group_id : ''; ?>" <?php echo $selected; ?>><?php echo isset($grp->group_name) ? $grp->group_name : ''; ?></option>
                                            <?php 
                                            		endforeach;
                                            	endif;
                                            ?>
                                            </select>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="exampleInputEmail1">Particular Name</label>
                                        	<?php echo form_input("partcular_name",set_value("partcular_name"),"class='form-control input-sm' placeholder='Particular Name' required style='width: 350px;'");?>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="exampleInputEmail1">Description</label>
                                        	<?php echo form_input("description",set_value("description"),"class='form-control input-sm' placeholder='Description' style='width: 350px;'");?>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="exampleInputEmail1">Amount</label>
                                        	<?php echo form_input("amount",set_value("amount"),"class='form-control input-sm' placeholder='eg 0.00' style='width: 350px;' required");?>
                                        </div>
                                        
                                        <hr style="border-top:2px solid #3c8dbc;">
                                        <h4 style="color:#3c8dbc;"><i class="fa fa-medkit"></i> NHIS Pricing</h4>

                                        <div class="form-group">
                                            <label>
                                                <input type="checkbox" name="is_nhis_covered" id="is_nhis_covered" value="1" <?php echo set_value('is_nhis_covered') ? 'checked' : ''; ?>> NHIS Covered Service
                                            </label>
                                        </div>

                                        <div class="form-group">
                                            <label for="nhis_charge_amount">NHIS Charge Amount</label>
                                            <input class="form-control input-sm" name="nhis_charge_amount" id="nhis_charge_amount" type="text" value="<?php echo set_value('nhis_charge_amount', '0.00'); ?>" placeholder="NHIS charge amount" style="width: 350px;">
                                        </div>

                                        <div class="form-group">
                                            <a href="<?php echo base_url();?>app/particular_bill" class="btn btn-default">Cancel</a>
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