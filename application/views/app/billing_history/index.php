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
                    <h1>Billing List</h1>
                    <ol class="breadcrumb">
                        <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                        <li><a href="#">Billing</a></li>
                        <li class="active">Billing List</li>
                    </ol>
                </section>

                <!-- Main content -->
                <section class="content">

                 <?php
                 $bs = isset($billing_summary) ? $billing_summary : array();
                 $oc = isset($outstanding_count) ? (int)$outstanding_count : 0;
                 ?>
                 <div class="row">
                    <div class="col-lg-2 col-xs-6">
                        <div class="small-box bg-aqua">
                            <div class="inner">
                                <h3><?php echo isset($bs['total_invoices']) ? (int)$bs['total_invoices'] : 0; ?></h3>
                                <p>Today's Invoices</p>
                            </div>
                            <div class="icon"><i class="fa fa-file-text-o"></i></div>
                        </div>
                    </div>
                    <div class="col-lg-2 col-xs-6">
                        <div class="small-box bg-green">
                            <div class="inner">
                                <h3><?php echo isset($bs['paid_count']) ? (int)$bs['paid_count'] : 0; ?></h3>
                                <p>Paid</p>
                            </div>
                            <div class="icon"><i class="fa fa-check-circle"></i></div>
                        </div>
                    </div>
                    <div class="col-lg-2 col-xs-6">
                        <div class="small-box bg-yellow">
                            <div class="inner">
                                <h3><?php echo isset($bs['partial_count']) ? (int)$bs['partial_count'] : 0; ?></h3>
                                <p>Partial</p>
                            </div>
                            <div class="icon"><i class="fa fa-adjust"></i></div>
                        </div>
                    </div>
                    <div class="col-lg-2 col-xs-6">
                        <div class="small-box bg-red">
                            <div class="inner">
                                <h3><?php echo isset($bs['unpaid_count']) ? (int)$bs['unpaid_count'] : 0; ?></h3>
                                <p>Unpaid</p>
                            </div>
                            <div class="icon"><i class="fa fa-exclamation-circle"></i></div>
                        </div>
                    </div>
                    <div class="col-lg-2 col-xs-6">
                        <div class="small-box bg-purple" style="background-color:#605ca8!important;">
                            <div class="inner" style="color:#fff;">
                                <h3 style="color:#fff;"><?php echo $oc; ?></h3>
                                <p>Outstanding</p>
                            </div>
                            <div class="icon"><i class="fa fa-clock-o"></i></div>
                        </div>
                    </div>
                    <div class="col-lg-2 col-xs-6">
                        <div class="small-box bg-green">
                            <div class="inner">
                                <h3><small style="font-size:18px;"><?php echo number_format(isset($bs['total_received']) ? (float)$bs['total_received'] : 0, 2); ?></small></h3>
                                <p>Received Today</p>
                            </div>
                            <div class="icon"><i class="fa fa-money"></i></div>
                        </div>
                    </div>
                 </div>
                 
                 <div class="row">
                 	<div class="col-md-12">
                    
                    	 <div class="box">
                         		
                         		<div class="box-body table-responsive no-padding">
                                    <h4 class="box-title">Search</h4>
                                    
                                    <div class="box-tools">
                                        <div class="input-group">
                                            <form method="post" action="<?php echo base_url()?>app/billing_history">
                                            <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                                            <table cellpadding="3" cellspacing="3" width="100%">
                                            <tr>
                                            	<td>From Date</td>
                                                <td>To Date</td>
                                                <td>Invoice No/LastName/FirstName</td>
                                                <td></td>
                                            </tr>
                                            <tr>
                                            	<td><input class="form-control input-sm" name="cFrom" id="cFrom" type="text" value="<?php echo date("Y-m-d");?>" placeholder="From Date Registration" style="width: 180px;" required></td>
                                                <td><input class="form-control input-sm" name="cTo" id="cTo" type="text" value="<?php echo date("Y-m-d");?>" placeholder="to Date Registration" style="width: 180px;" required></td>
                                            	
                                                <td>
                                                <input type="text" class="form-control input-sm" name="search" id="search" placeholder="Invoice No/LastName/FirstName" style="width: 180px;">
                                                </td>
                                                <td>
                                                <button class="btn btn-sm btn-primary" name="btnSearch" id="btnSearch" type="submit"><i class="fa fa-search"></i> Search </button>
                                                </td>
                                            </tr>
                                            </table>
                                            </form>
                                        </div>
                                    </div>
                                    
                                </div><!-- /.box-header -->
                                
								
                            
                            <div class="box-footer clearfix">
                                	
                                </div>
                        </div>
                    </div>
                    
                    <div class="col-md-12">
                    
                    	 <div class="box">
                        	<div class="box-body table-responsive no-padding">
                            	<?php echo $message;?>
                                
                            	<?php echo $table; ?>
                                
                            </div>
                            	<div class="box-footer clearfix">
                                	<?php echo $pagination; ?>
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
                
                $('#cFrom').datepicker({
                    //format: "dd/mm/yyyy"
					format: "yyyy-mm-dd"
                });  
				
				$('#cTo').datepicker({
                    //format: "dd/mm/yyyy"
					format: "yyyy-mm-dd"
                });  
            
            });
        </script>
        <!-- END BDAY -->
        
        
    </body>
</html>