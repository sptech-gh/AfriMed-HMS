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
        
        <link href="<?php echo base_url();?>public/css/datatables/dataTables.bootstrap.css" rel="stylesheet" type="text/css" />
        
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
                    <h1>Pages</h1>
                    <!--<ol class="breadcrumb">
                        <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
                        <li><a href="#">Examples</a></li>
                        <li class="active">Blank page</li>
                    </ol>-->
                </section>

                <!-- Main content -->
                <section class="content">
                 
                 <div class="row">
                 	<div class="col-md-12">
                    	<div class="box box-primary">
                        	<div class="box-header">
                            	<div class="pull-right box-tools">                                        
                                        <button class="btn btn-primary btn-sm pull-right" data-widget='collapse' data-toggle="tooltip" title="Collapse" style="margin-right: 5px;"><i class="fa fa-minus"></i></button>
                                </div><!-- /. tools -->
                                    
                            	<h2 class="box-title">Filter</h2>
                            </div>
                            <div class="box-body no-padding">
                            	<div class="table-responsive">
                                	<input type="text">
                                </div>
                            </div>
                        </div>
                    </div>
                 </div>
                 
                 <div class="row">
                 	<div class="col-md-12">
                    	 <div class="box">
                        	<div class="box-body table-responsive">
                            	<table id="example2" class="table table-bordered table-hover">
                                	<thead>
                                    	<tr>
                                        	<th>Module</th>
                                            <th>Page Name</th>
                                            <th>Page Link</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    	<tr>
                                        	<th>Patient</th>
                                            <th>Add Patient</th>
                                            <th>Add_Patient</th>
                                        </tr>
                                        <tr>
                                        	<th>Patient</th>
                                            <th>Update Patient</th>
                                            <th>Update_Patient</th>
                                        </tr>
                                        <tr>
                                        	<th>Patient</th>
                                            <th>Add Patient</th>
                                            <th>Add_Patient</th>
                                        </tr>
                                        <tr>
                                        	<th>Patient</th>
                                            <th>Update Patient</th>
                                            <th>Update_Patient</th>
                                        </tr>
                                        <tr>
                                        	<th>Patient</th>
                                            <th>Add Patient</th>
                                            <th>Add_Patient</th>
                                        </tr>
                                        <tr>
                                        	<th>Patient</th>
                                            <th>Update Patient</th>
                                            <th>Update_Patient</th>
                                        </tr>
                                        <tr>
                                        	<th>Patient</th>
                                            <th>Add Patient</th>
                                            <th>Add_Patient</th>
                                        </tr>
                                        <tr>
                                        	<th>Patient</th>
                                            <th>Update Patient</th>
                                            <th>Update_Patient</th>
                                        </tr>
                                        <tr>
                                        	<th>Patient</th>
                                            <th>Add Patient</th>
                                            <th>Add_Patient</th>
                                        </tr>
                                        <tr>
                                        	<th>Patient</th>
                                            <th>Update Patient</th>
                                            <th>Update_Patient</th>
                                        </tr>
                                        <tr>
                                        	<th>Patient</th>
                                            <th>Add Patient</th>
                                            <th>Add_Patient</th>
                                        </tr>
                                        <tr>
                                        	<th>Patient</th>
                                            <th>Update Patient</th>
                                            <th>Update_Patient</th>
                                        </tr>
                                        <tr>
                                        	<th>Patient</th>
                                            <th>Add Patient</th>
                                            <th>Add_Patient</th>
                                        </tr>
                                        <tr>
                                        	<th>Patient</th>
                                            <th>Update Patient</th>
                                            <th>Update_Patient</th>
                                        </tr>
                                        <tr>
                                        	<th>Patient</th>
                                            <th>Add Patient</th>
                                            <th>Add_Patient</th>
                                        </tr>
                                        <tr>
                                        	<th>Patient</th>
                                            <th>Update Patient</th>
                                            <th>Update_Patient</th>
                                        </tr>
                                        <tr>
                                        	<th>Patient</th>
                                            <th>Add Patient</th>
                                            <th>Add_Patient</th>
                                        </tr>
                                        <tr>
                                        	<th>Patient</th>
                                            <th>Update Patient</th>
                                            <th>Update_Patient</th>
                                        </tr><tr>
                                        	<th>Patient</th>
                                            <th>Add Patient</th>
                                            <th>Add_Patient</th>
                                        </tr>
                                        <tr>
                                        	<th>Patient</th>
                                            <th>Update Patient</th>
                                            <th>Update_Patient</th>
                                        </tr>
                                        <tr>
                                        	<th>Patient</th>
                                            <th>Add Patient</th>
                                            <th>Add_Patient</th>
                                        </tr>
                                        <tr>
                                        	<th>Patient</th>
                                            <th>Update Patient</th>
                                            <th>Update_Patient</th>
                                        </tr>
                                        <tr>
                                        	<th>Patient</th>
                                            <th>Add Patient</th>
                                            <th>Add_Patient</th>
                                        </tr>
                                        <tr>
                                        	<th>Patient</th>
                                            <th>Update Patient</th>
                                            <th>Update_Patient</th>
                                        </tr>
                                        <tr>
                                        	<th>Patient</th>
                                            <th>Add Patient</th>
                                            <th>Add_Patient</th>
                                        </tr>
                                        <tr>
                                        	<th>Patient</th>
                                            <th>Update Patient</th>
                                            <th>Update_Patient</th>
                                        </tr>
                                        <tr>
                                        	<th>Patient</th>
                                            <th>Add Patient</th>
                                            <th>Add_Patient</th>
                                        </tr>
                                        <tr>
                                        	<th>Patient</th>
                                            <th>Update Patient</th>
                                            <th>Update_Patient</th>
                                        </tr>
                                        <tr>
                                        	<th>Patient</th>
                                            <th>Add Patient</th>
                                            <th>Add_Patient</th>
                                        </tr>
                                        <tr>
                                        	<th>Patient</th>
                                            <th>Update Patient</th>
                                            <th>Update_Patient</th>
                                        </tr>
                                        <tr>
                                        	<th>Patient</th>
                                            <th>Add Patient</th>
                                            <th>Add_Patient</th>
                                        </tr>
                                        <tr>
                                        	<th>Patient</th>
                                            <th>Update Patient</th>
                                            <th>Update_Patient</th>
                                        </tr>
                                        <tr>
                                        	<th>Patient</th>
                                            <th>Add Patient</th>
                                            <th>Add_Patient</th>
                                        </tr>
                                        <tr>
                                        	<th>Patient</th>
                                            <th>Update Patient</th>
                                            <th>Update_Patient</th>
                                        </tr>
                                        <tr>
                                        	<th>Patient</th>
                                            <th>Add Patient</th>
                                            <th>Add_Patient</th>
                                        </tr>
                                        <tr>
                                        	<th>Patient</th>
                                            <th>Update Patient</th>
                                            <th>Update_Patient</th>
                                        </tr>
                                        <tr>
                                        	<th>Patient</th>
                                            <th>Add Patient</th>
                                            <th>Add_Patient</th>
                                        </tr>
                                        <tr>
                                        	<th>Patient</th>
                                            <th>Update Patient</th>
                                            <th>Update_Patient</th>
                                        </tr>
                                        <tr>
                                        	<th>Patient</th>
                                            <th>Add Patient</th>
                                            <th>Add_Patient</th>
                                        </tr>
                                        <tr>
                                        	<th>Patient</th>
                                            <th>Update Patient</th>
                                            <th>Update_Patient</th>
                                        </tr>
                                        <tr>
                                        	<th>Patient</th>
                                            <th>Add Patient</th>
                                            <th>Add_Patient</th>
                                        </tr>
                                        <tr>
                                        	<th>Patient</th>
                                            <th>Update Patient</th>
                                            <th>Update_Patient</th>
                                        </tr>
                                        <tr>
                                        	<th>Patient</th>
                                            <th>Add Patient</th>
                                            <th>Add_Patient</th>
                                        </tr>
                                        <tr>
                                        	<th>Patient</th>
                                            <th>Update Patient</th>
                                            <th>Update_Patient</th>
                                        </tr>
                                        <tr>
                                        	<th>Patient</th>
                                            <th>Add Patient</th>
                                            <th>Add_Patient</th>
                                        </tr>
                                        <tr>
                                        	<th>Patient</th>
                                            <th>Update Patient</th>
                                            <th>Update_Patient</th>
                                        </tr>
                                    </tbody>
                                    <tfoot>
                                    	<tr>
                                        	<th>Module</th>
                                            <th>Page Name</th>
                                            <th>Page Link</th>
                                        </tr>
                                    </tfoot>
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
        <script src="<?php echo base_url();?>public/js/AdminLTE/demo.js" type="text/javascript"></script>
        
        <script src="<?php echo base_url();?>public/js/plugins/datatables/jquery.dataTables.js" type="text/javascript"></script>
        <script src="<?php echo base_url();?>public/plugins/datatables/dataTables.bootstrap.js" type="text/javascript"></script>
        <script type="text/javascript">
            $(function() {
                $("#example1").dataTable();
                $('#example2').dataTable({
                    "bPaginate": true,
                    "bLengthChange": false,
                    "bFilter": false,
                    "bSort": true,
                    "bInfo": true,
                    "bAutoWidth": false
                });
            });
        </script>
    </body>
</html>