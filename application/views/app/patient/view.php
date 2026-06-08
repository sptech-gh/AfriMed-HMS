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
                    <h1>Patient Information Details</h1>
                    <ol class="breadcrumb">
                        <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                        <li><a href="#">Patient Management</a></li>
                        <li><a href="<?php echo base_url()?>app/patient">Patient Master</a></li>
                        <li class="active">Patient Information Details</li>
                    </ol>
                </section>

                <!-- Main content -->
                <section class="content">
                 
                 
                 <div class="row">
                 	<div class="col-md-12">
                    
                    	 <div class="box">
                         		
                         		<div class="box-header">
                                    <h3 class="box-title">
                                    <a href="<?php echo base_url();?>app/patient/" class="btn btn-default"><i class="fa fa-arrow-left"></i> Back</a>	
                                    <a href="<?php echo base_url();?>app/patient/edit/<?php echo $patientInfo->patient_no?>" class="btn btn-primary"><i class="fa fa-edit"></i> Edit Information</a>
                                    <a href="<?php echo base_url();?>app/patient_history/index/<?php echo $patientInfo->patient_no?>" class="btn btn-success"><i class="fa fa-history"></i> Patient History</a>
                                    </h3>
                                    
                                    <div class="box-tools">
                                        <div class="input-group">
                                            
                                        </div>
                                    </div>
                                    
                                </div><!-- /.box-header -->
								
                        	<div class="box-body table-responsive">
                            
                            	<div class="nav-tabs-custom">
                                	<ul class="nav nav-tabs">
                                		<li class="active"><a href="#tab_1" data-toggle="tab">Personal Information</a></li>
                                    	<li><a href="#tab_2" data-toggle="tab">Contact Information</a></li>
                                    	<li><a href="#tab_3" data-toggle="tab">Other Information</a></li>
                                    	<li><a href="#tab_4" data-toggle="tab">Attachment</a></li>
                                	</ul>
                                    
                                    <div class="tab-content">
                                    	<div class="tab-pane active" id="tab_1">
                                            <div class="table-responsive">
                                        	<table cellpadding="3" cellspacing="3" align="center" width="100%">
                                	<tr>
                                		<td width="15%">Patient No.</td>
                                        <td width="40%"><?php echo $patientInfo->patient_no?></td>
                                        <td width="45%" rowspan="6" align="center">
    									<?php
											if(!$patientInfo->picture){
												$picture = "avatar.png";	
											}else{
												$picture = $patientInfo->picture;
											}
										?>
										<img src="<?php echo base_url();?>public/patient_picture/<?php echo $picture;?>" class="img-rounded" width="150" height="150">
    									</td>
                                	</tr>
                                    <tr>
                                		<td width="15%">Patient Name</td>
                                        <td width="40%"><?php echo $patientInfo->name?></td>
                                	</tr>
                                    <tr>
                                    	<td width="15%">Gender</td>
                                        <td width="40%"><?php echo $patientInfo->gender?></td>
                                    </tr>
                                    <tr>
                                    	<td width="15%">Age</td>
                                        <td width="40%"><?php echo $patientInfo->age?></td>
                                    </tr>
                                    <tr>
                                		<td width="15%">Address</td>
                                        <td><?php echo $patientInfo->address ? htmlspecialchars($patientInfo->address) : '<span style="color:#999;">—</span>'; ?></td>
                                	</tr>
                                    <tr>
                                		<td width="15%">Civil Status</td>
                                        <td width="40%"><?php echo $patientInfo->civil_status ? htmlspecialchars($patientInfo->civil_status) : '<span style="color:#999;">—</span>'; ?></td>
                                	</tr>
                                    <tr>
                                    	<td width="15%">Religion</td>
                                        <td width="40%"><?php echo $patientInfo->religion?></td>
                                    </tr>
                                    <tr>
                                		<td width="15%">Birthday</td>
                                        <td width="40%"><?php echo date('M d, Y',strtotime($patientInfo->birthday));?></td>
                                        
                                	</tr>
                                    <tr>
                                    	<td width="15%">Birth Place</td>
                                        <td width="40%"><?php echo $patientInfo->birthplace?></td>
                                    </tr>
                                    <tr>
                                    	<td>Blood Group</td>
                                        <td><?php echo $patientInfo->blood_group?></td>
                                    </tr>
                                	</table>
                                            </div>
                                        </div>
                                        <div class="tab-pane" id="tab_2">
                                            <div class="table-responsive">
                                        	<table cellpadding="3" cellspacing="3" align="center" width="100%">
                                     <tr>
                                     	<td width="21%">Phone No (Home)</td>
                                        <td width="79%"><?php echo $patientInfo->phone_no ? htmlspecialchars($patientInfo->phone_no) : '<span style="color:#999;">—</span>'; ?></td>
                                     </tr>
                                     <tr>
                                     	<td>Phone No (Office)</td>
                                        <td><?php echo $patientInfo->phone_no_office ? htmlspecialchars($patientInfo->phone_no_office) : '<span style="color:#999;">—</span>'; ?></td>
                                     </tr>
                                     <tr>
                                     	<td>Phone No (Mobile)</td>
                                        <td><?php echo $patientInfo->mobile_no ? htmlspecialchars($patientInfo->mobile_no) : '<span style="color:#999;">—</span>'; ?></td>
                                     </tr>
                                     <tr>
                                     	<td>Email Address</td>
                                        <td><?php echo $patientInfo->email_address ? htmlspecialchars($patientInfo->email_address) : '<span style="color:#999;">—</span>'; ?></td>
                                     </tr>
                                     <tr>
                                     	<td colspan="2"><hr style="margin:5px 0; border-top:1px dashed #ccc;"></td>
                                     </tr>
                                     <tr>
                                     	<td><strong>Emergency Contact</strong></td>
                                        <td><?php echo $patientInfo->emergency_fullname ? htmlspecialchars($patientInfo->emergency_fullname) : '<span style="color:#999;">—</span>'; ?></td>
                                     </tr>
                                     <tr>
                                     	<td>Emergency Phone</td>
                                        <td><?php echo $patientInfo->emergency_phone_number ? htmlspecialchars($patientInfo->emergency_phone_number) : '<span style="color:#999;">—</span>'; ?></td>
                                     </tr>
                                     </table>
                                            </div>
                                        </div>
                                        <div class="tab-pane" id="tab_3">
                                            <div class="table-responsive">
                                        	<table cellpadding="3" cellspacing="3" align="center" width="100%">
                                     <?php
                                        $nhis_num = isset($patientInfo->nhis_number) ? $patientInfo->nhis_number : '';
                                        $nhis_exp = isset($patientInfo->nhis_expiry_date) ? $patientInfo->nhis_expiry_date : '';
                                        $nhis_st  = isset($patientInfo->nhis_status) ? strtoupper(trim((string)$patientInfo->nhis_status)) : '';
                                     ?>
                                     <?php if ($nhis_num != ''): ?>
                                     <tr>
                                     	<td width="21%"><strong>NHIS Status</strong></td>
                                        <td width="79%">
                                            <?php if ($nhis_st === 'ACTIVE'): ?>
                                                <span class="label label-success" style="font-size:13px;padding:4px 10px;"><i class="fa fa-check-circle"></i> NHIS Active</span>
                                            <?php elseif ($nhis_st === 'EXPIRED'): ?>
                                                <span class="label label-danger" style="font-size:13px;padding:4px 10px;"><i class="fa fa-exclamation-triangle"></i> NHIS Expired</span>
                                                <span style="color:#c9302c;margin-left:8px;"><i class="fa fa-warning"></i> Billing defaults to CASH</span>
                                            <?php elseif ($nhis_st === 'INVALID'): ?>
                                                <span class="label label-warning" style="font-size:13px;padding:4px 10px;"><i class="fa fa-ban"></i> NHIS Invalid</span>
                                            <?php else: ?>
                                                <span class="label label-default" style="font-size:13px;padding:4px 10px;">NHIS Unknown</span>
                                            <?php endif; ?>
                                        </td>
                                     </tr>
                                     <tr>
                                     	<td width="21%"><strong>NHIS Number</strong></td>
                                        <td width="79%"><?php echo htmlspecialchars($nhis_num); ?></td>
                                     </tr>
                                     <tr>
                                     	<td width="21%"><strong>NHIS Expiry</strong></td>
                                        <td width="79%"><?php echo ($nhis_exp && $nhis_exp !== '0000-00-00') ? date('M d, Y', strtotime($nhis_exp)) : '<span style="color:#999;">Not set</span>'; ?></td>
                                     </tr>
                                     <tr><td colspan="2"><hr style="margin:5px 0;border-top:1px dashed #ccc;"></td></tr>
                                     <?php endif; ?>
                                     <tr>
                                     	<td width="21%">Company Insurance</td>
                                        <td width="79%"><?php echo $patientInfo->company_name?></td>
                                     </tr>
                                     <tr>
                                     	<td>Isurance ID No.</td>
                                        <td><?php echo $patientInfo->insurance_no?></td>
                                     </tr>
                                     <tr>
                                     	<td>Patient Identifiers</td>
                                        <td><?php echo $patientInfo->id_identifiers?></td>
                                     </tr>
                                      </table>
                                      </div>
                                         </div>
                                        <div class="tab-pane" id="tab_4">
                                        	<iframe width="100%" frameborder="0" height="400" src="<?php echo base_url()?>app/patient/attachment/<?php echo $patientInfo->patient_no?>"></iframe>
                                        </div>
                                    </div>
                                </div>
                                
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