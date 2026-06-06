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
                    <h1>Insurance Company Details</h1>
                    <ol class="breadcrumb">
                        <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                        <li><a href="#">Admin</a></li>
                        <li><a href="<?php echo base_url()?>app/insurance_company">Insurance Company Master</a></li>
                        <li class="active">Insurance Company Details</li>
                    </ol>
                </section>
				
                <!-- Main content -->
                <section class="content">
                 
                 
                 <div class="row">
                 	<div class="col-md-12">
                    
                    	<form role="form" method="post" action="<?php echo base_url()?>app/insurance_company/save" onSubmit="return confirm('Are you sure you want to save?');">
                    <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                    	 <div class="box">
                         		
                         		<div class="box-header">
                                    <h3 class="box-title">
                                    <a href="<?php echo base_url();?>app/insurance_company" class="btn btn-default">Cancel</a>
                                    </h3>
                                    
                                </div>
                        	<div class="box-body table-responsive">
                            	
                                
                                		<table class="table table-striped">
                                        <tbody>
                                        <tr>
                                        	<td width="16%">Company Name <font color="#FF0000">*</font></td>
                                            <td width="84%"><?php echo $insurance_companyList->company_name?></td>
                                        </tr>
                                        <tr>
                                        	<td>Email Address <font color="#FF0000">*</font></td>
                                            <td><?php echo $insurance_companyList->email_address?></td>
                                        </tr>
                                        <tr>
                                        	<td>Phone No. <font color="#FF0000">*</font></td>
                                            <td><?php echo $insurance_companyList->phone_no?></td>
                                        </tr>
                                        <tr>
                                        	<td>Fax No.</td>
                                            <td><?php echo $insurance_companyList->fax_no?></td>
                                        </tr>
                                        <tr>
                                            <td>Pricing Adjustment (%)</td>
                                            <td>
                                                <?php 
                                                $pricing_pct = isset($insurance_companyList->pricing_percentage) ? $insurance_companyList->pricing_percentage : '0.00';
                                                if ($pricing_pct > 0) {
                                                    echo '<span class="label label-warning">+' . number_format($pricing_pct, 2) . '% Markup</span>';
                                                } elseif ($pricing_pct < 0) {
                                                    echo '<span class="label label-success">' . number_format($pricing_pct, 2) . '% Discount</span>';
                                                } else {
                                                    echo '<span class="label label-default">0.00% (Standard Pricing)</span>';
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                        <tr>
                                        	<td valign="top">Company Address <font color="#FF0000">*</font></td>
                                            <td><?php echo $insurance_companyList->company_address?></td>
                                        </tr>
                                        <tr>
                                        	<td colspan="2"><hr>CONTACT PERSON DETAILS</td>
                                        </tr>
                                        <tr>
                                        	<td>Contact Person <font color="#FF0000">*</font></td>
                                            <td><?php echo $insurance_companyList->contact_person?></td>
                                        </tr>
                                        <tr>
                                        	<td>Contact No. <font color="#FF0000">*</font></td>
                                            <td><?php echo $insurance_companyList->contact_no_person?></td>
                                        </tr>
                                        <tr>
                                        	<td>Email Address <font color="#FF0000">*</font></td>
                                            <td><?php echo $insurance_companyList->contact_email?></td>
                                        </tr>
                                        <tr>
                                        	<td colspan="2"><hr></td>
                                        </tr>
                                        <tr>
                                        	<td valign="top">Remarks</td>
                                            <td><?php echo $insurance_companyList->notes?></td>
                                        </tr>
                                        </tbody>
                                        </table>
                                        
                                
                                
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
        
    </body>
</html>