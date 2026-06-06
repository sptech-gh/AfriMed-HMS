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
                    <h1><?php echo strtoupper($module);?></h1>
                    <ol class="breadcrumb">
                        <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                        <li><a href="#">Administrator</a></li>
                        <li><a href="<?php echo base_url()?>app/parameters">System Parameters</a></li>
                        <li class="active"><?php echo strtoupper($module);?></li>
                    </ol>
                </section>

                <!-- Main content -->
                <section class="content">
                 
                 
                 <div class="row">
                 	<div class="col-md-12">
                    
                    	 <div class="box">
                         		<form class="form-search" method="post" action="<?php echo base_url();?>app/user">
                         		<div class="box-header">
                                    <h3 class="box-title"><a href="<?php echo base_url();?>app/parameters/add/<?php echo $module;?>" class="btn btn-primary"><i class="fa fa-plus"></i> Add New</a></h3>
                                    
                                  
                                    
                                </div><!-- /.box-header -->
								</form>
                        	<div class="box-body table-responsive">
                            	
								<?php echo $message;?>
                                
                            	<table class="table table-hover table-striped">
                                <thead>
                                	<tr>
                                    	<th>CODE</th>
                                        <th>VALUE</th>
                                        <th>DESCRIPTION</th>
                                        <th>ACTIONS</th>
                                    </tr>
                                </thead>
                                <tbody>
                                	<?php foreach($lists as $lists){?>
                                	<tr>
                                    	<td><?php echo $lists->cCode?></td>
                                        <td><?php echo $lists->cValue?></td>
                                        <td><?php echo $lists->cDesc?></td>
                                        <td>
                                        <a href="<?php echo base_url()?>app/parameters/edit/<?php echo $lists->param_id?>">Edit</a>&nbsp;|&nbsp;
                                        <form method="post" action="<?php echo base_url()?>app/parameters/delete/<?php echo $lists->cCode?>/<?php echo $lists->param_id?>" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete?');">
                                            <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                                            <button type="submit" class="btn btn-xs btn-danger">Delete</button>
                                        </form>
                                        </td>
                                    </tr>
                                    <?php }?>
                                </tbody>
                                </table>
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
        
    </body>
</html>