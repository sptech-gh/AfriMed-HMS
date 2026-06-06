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
                    <h1>Room Master</h1>
                    <ol class="breadcrumb">
                        <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                        
                        <li><a href="#">Room Management</a></li>
                        <li><a href="<?php echo base_url()?>app/room_master">Room Master</a></li>
                        <li class="active">Room Master Details</li>
                    </ol>
                </section>
				
                <!-- Main content -->
                <section class="content">
                 
                 
                 <div class="row">
                 	<div class="col-md-12">
                    
                    	 <div class="box">
                         		
                         		<div class="box-header">
                                    <h3 class="box-title">
                                    <a href="<?php echo base_url();?>app/room_master" class="btn btn-default" >Back</a>
                                    <a href="#" class="btn btn-primary" data-toggle="modal" data-target="#myModal">Price History</a>
                                    </h3>
                                    
                                </div>
                        	<div class="box-body table-responsive">
                            	
                               <table class="table table-striped">
                                <tr>
                                	<td width="20%">Category Name</td>
                                    <td width="80%"><?php echo $getRoomInfo->category_name?></td>
                                </tr>
                                <tr>
                                	<td>Room Name</td>
                                    <td><?php echo $getRoomInfo->room_name?></td>
                                </tr>
                                <tr>
                                	<td>Floor</td>
                                    <td><?php echo $getRoomInfo->floor_name?></td>
                                </tr>
                                <tr>
                                	<td>Room Rates</td>
                                    <td><?php echo $getRoomInfo->room_rates?></td>
                                </tr>
                                </table>
                                
                                
                                
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
        
        
<div class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
        
        <h4 class="modal-title" id="myModalLabel">Price History</h4>
      </div>
      <div class="modal-body">
      <center>
      <table class="table table-striped">
      <thead>
      	<tr>
        	<th>Price</th>
            <th>Updated by</th>
            <th>Date Added</th>
        </tr>
        <tbody>
        <?php foreach($getPriceHistory as $getPriceHistory){?>
        <tr>
        	<td><?php echo $getPriceHistory->price?></td>
            <td><?php echo $getPriceHistory->name?></td>
            <td><?php echo date("M d, Y h:i:s", strtotime($getPriceHistory->dDate));?></td>
        </tr>
        <?php }?>
        </tbody>
      </thead>
      </table>
      </center>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary">Apply</button>
      </div>
    </div>
  </div>
</div>        
        
        
    </body>
</html>