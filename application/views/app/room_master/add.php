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
                    <h1>Add Room Master</h1>
                    <ol class="breadcrumb">
                        <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                       
                        <li><a href="#">Room Management</a></li>
                        <li><a href="<?php echo base_url()?>app/room_master">Room Master</a></li>
                        <li class="active">Add Room Master</li>
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
                            	<form role="form" method="post" action="<?php echo base_url()?>app/room_master/save" onSubmit="return confirm('Are you sure you want to save?');">
                                
                                		<?php echo validation_errors(); ?>
                                		<input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">   
                                
                                		<div class="form-group">
                                            <label for="exampleInputEmail1">Room No/Name</label>
                                            <input class="form-control input-sm" name="room_name" id="room_name" type="text" placeholder="Room No/Name" style="width: 250px;" required>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="exampleInputEmail1">Room Type</label>
                                            				<select name="roomType" id="roomType" class="form-control input-sm" style="width: 250px;" required>
                                                            	<option value="">- Room Type -</option>
																<?php 
																foreach($roomTypeList as $roomTypeList){
																if(isset($_POST['room_type']) && $_POST['room_type'] == $roomTypeList->category_id){
																	$selected = "selected='selected'";
																}else{
																	$selected = "";
																}
																?>
                                                            	<option value="<?php echo $roomTypeList->category_id;?>" <?php echo $selected;?>><?php echo $roomTypeList->category_name;?></option>
                                                                <?php }?>
                                                            </select>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="exampleInputEmail1">Floor</label>
                                            				<select name="floor" id="floor" class="form-control input-sm" style="width: 250px;" required>
                                                            	<option value="">- Floor -</option>
																<?php 
																foreach($floorList as $floorList){
																if(isset($_POST['floor']) && $_POST['floor'] == $floorList->floor_id){
																	$selected = "selected='selected'";
																}else{
																	$selected = "";
																}
																?>
                                                            	<option value="<?php echo $floorList->floor_id;?>" <?php echo $selected;?>><?php echo $floorList->floor_name;?></option>
                                                                <?php }?>
                                                            </select>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="exampleInputEmail1">Room Rate</label>
                                            <input class="form-control input-sm" name="room_rates" id="room_rates" type="text" placeholder="eg 9999.00" style="width: 250px;" required>
                                        </div>
                                        
                                        <div class="form-group">
                                            <a href="<?php echo base_url();?>app/room_master" class="btn btn-default">Cancel</a>
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