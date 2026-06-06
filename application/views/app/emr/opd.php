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
                    <h1>Out-Patient EMR</h1>
                    <ol class="breadcrumb">
                        <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                        <li><a href="#">EMR sheet</a></li>
                        <li><a href="#">OPD</a></li>
                    </ol>
                </section>

                <!-- Main content -->
                <section class="content">
                 
                 <?php if (isset($is_admin) && $is_admin && isset($visit_stats)): ?>
                 <!-- Visit Statistics for Admin -->
                 <div class="row">
                    <div class="col-md-3 col-sm-6">
                        <div class="small-box bg-aqua">
                            <div class="inner">
                                <h3><?php echo $visit_stats['today_pending']; ?></h3>
                                <p>Today's Pending</p>
                            </div>
                            <div class="icon"><i class="fa fa-clock-o"></i></div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="small-box bg-yellow">
                            <div class="inner">
                                <h3><?php echo $visit_stats['total_pending']; ?></h3>
                                <p>Total Pending</p>
                            </div>
                            <div class="icon"><i class="fa fa-users"></i></div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="small-box bg-red">
                            <div class="inner">
                                <h3><?php echo $visit_stats['old_pending_7d']; ?></h3>
                                <p>Pending > 7 Days</p>
                            </div>
                            <div class="icon"><i class="fa fa-exclamation-triangle"></i></div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="small-box bg-green">
                            <div class="inner">
                                <h3><?php echo $visit_stats['today_discharged']; ?></h3>
                                <p>Today's Discharged</p>
                            </div>
                            <div class="icon"><i class="fa fa-check-circle"></i></div>
                        </div>
                    </div>
                 </div>
                 
                 <!-- Bulk Actions for Admin -->
                 <div class="row">
                    <div class="col-md-12">
                        <div class="box box-warning collapsed-box">
                            <div class="box-header with-border">
                                <h3 class="box-title"><i class="fa fa-cogs"></i> Bulk Actions (Admin)</h3>
                                <div class="box-tools pull-right">
                                    <button class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-plus"></i></button>
                                </div>
                            </div>
                            <div class="box-body">
                                <form method="post" action="<?php echo base_url(); ?>app/opd/bulk_close_visits" class="form-inline">
                                    <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                                    <div class="form-group">
                                        <label>Close visits older than</label>
                                        <select name="days_old" class="form-control">
                                            <option value="7">7 days</option>
                                            <option value="14">14 days</option>
                                            <option value="30" selected>30 days</option>
                                            <option value="60">60 days</option>
                                            <option value="90">90 days</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Reason</label>
                                        <select name="bulk_reason" class="form-control">
                                            <option value="abandoned">Patient Abandoned Visit</option>
                                            <option value="no_show">Patient Did Not Show Up</option>
                                            <option value="admin_close">Administrative Closure</option>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-warning" onclick="return confirm('Are you sure you want to close all old pending visits? This action cannot be undone.');">
                                        <i class="fa fa-times-circle"></i> Bulk Close Old Visits
                                    </button>
                                    <span class="text-muted" style="margin-left: 15px;">
                                        <i class="fa fa-info-circle"></i> <?php echo $visit_stats['old_pending_30d']; ?> visits are older than 30 days
                                    </span>
                                </form>
                            </div>
                        </div>
                    </div>
                 </div>
                 <?php endif; ?>
                 
                 <div class="row">
                 	<div class="col-md-12">
                    
                    	 <div class="box">
                         		
                         		<div class="box-body table-responsive no-padding">
                                    <h4 class="box-title">Search OPD Patient</h4>
                                    
                                    <div class="box-tools">
                                        <div class="input-group">
                                            <form method="post" action="">
                                            <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                                            <table cellpadding="3" cellspacing="3" width="100%">
                                            <tr>
                                            	<td>From Date</td>
                                                <td>To Date</td>
                                                <td>Department</td>
                                                <td>Consultant Doctor</td>
                                                <td>Insurance</td>
                                                <td>OPD/LastName/FirstName</td>
                                                <td></td>
                                            </tr>
                                            <tr>
                                            	<td><input class="form-control input-sm" name="cFrom" id="cFrom" type="text" value="<?php echo date("Y-m-d");?>" placeholder="From Date Registration" style="width: 130px;" required></td>
                                                <td><input class="form-control input-sm" name="cTo" id="cTo" type="text" value="<?php echo date("Y-m-d");?>" placeholder="to Date Registration" style="width: 130px;" required></td>
                                            	<td>
                                                <select name="department" id="department" class="form-control input-sm" style="width: 200px;">
                                                            	<option value="">All Department</option>
                                                            	<?php 
																foreach($departmentList as $departmentList){
																if(isset($_POST['department']) && $_POST['department'] == $departmentList->department_id){
																	$selected = "selected='selected'";
																}else{
																	$selected = "";
																}
																?>
                                                            	<option value="<?php echo $departmentList->department_id;?>" <?php echo $selected;?>><?php echo $departmentList->dept_name;?></option>
                                                                <?php }?>
                                                            </select>
                                                </td>
                                                <td>
                                                <select name="doctor" id="doctor" class="form-control input-sm" style="width: 200px;">
                                                            	<option value="">All Doctors</option>
                                                            	<?php 
																foreach($doctorList as $doctorList){
																if(isset($_POST['doctor']) && $_POST['doctor'] == $doctorList->user_id){
																	$selected = "selected='selected'";
																}else{
																	$selected = "";
																}
																?>
                                                            	<option value="<?php echo $doctorList->user_id;?>" <?php echo $selected;?>><?php echo $doctorList->name;?></option>
                                                                <?php }?>
                                                            </select>
                                                </td>
                                                <td>
                                                <select name="insurance" id="insurance" class="form-control input-sm" style="width: 200px;">
                                                            	<option value="">All Insurance</option>
                                                            	<?php 
																foreach($insuranceCompList as $insuranceCompList){
																if(isset($_POST['insurance']) && $_POST['insurance'] == $insuranceCompList->in_com_id){
																	$selected = "selected='selected'";
																}else{
																	$selected = "";
																}
																?>
                                                            	<option value="<?php echo $insuranceCompList->in_com_id;?>" <?php echo $selected;?>><?php echo $insuranceCompList->company_name;?></option>
                                                                <?php }?>
                                                            </select>
                                                </td>
                                                <td>
                                                <input type="text" class="form-control input-sm" name="search" id="search" placeholder="OPD/LastName/FirstName" style="width: 180px;">
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
  
    <?php if (isset($is_admin) && $is_admin): ?>
    <!-- Close Visit Modal -->
    <div class="modal fade" id="closeVisitModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form method="post" action="" id="closeVisitForm">
                    <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                    <div class="modal-header bg-red">
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                        <h4 class="modal-title"><i class="fa fa-times-circle"></i> Close Visit</h4>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <i class="fa fa-exclamation-triangle"></i> You are about to close the visit for: <strong id="closePatientName"></strong>
                        </div>
                        <div class="form-group">
                            <label>Reason for Closing <span class="text-danger">*</span></label>
                            <select name="close_reason" class="form-control" required>
                                <option value="">-- Select Reason --</option>
                                <?php if (isset($closure_reasons)): foreach ($closure_reasons as $key => $label): ?>
                                <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                                <?php endforeach; endif; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Additional Notes</label>
                            <textarea name="close_notes" class="form-control" rows="3" placeholder="Optional notes about this closure..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger"><i class="fa fa-times"></i> Close Visit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reopen Visit Modal -->
    <div class="modal fade" id="reopenVisitModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header bg-info">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title"><i class="fa fa-refresh"></i> Reopen Visit</h4>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fa fa-info-circle"></i> You are about to reopen the visit for: <strong id="reopenPatientName"></strong>
                    </div>
                    <p>This will change the visit status back to "Pending" and allow further clinical actions.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <a href="#" id="reopenVisitLink" class="btn btn-info"><i class="fa fa-refresh"></i> Reopen Visit</a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
        
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
            
                <?php if (isset($is_admin) && $is_admin): ?>
                // Close Visit button handler
                $(document).on('click', '.close-visit-btn', function() {
                    var iop = $(this).data('iop');
                    var patient = $(this).data('patient');
                    var name = $(this).data('name');
                    
                    $('#closePatientName').text(name + ' (' + iop + ')');
                    $('#closeVisitForm').attr('action', '<?php echo base_url(); ?>app/opd/close_visit/' + iop + '/' + patient);
                    $('#closeVisitModal').modal('show');
                });

                // Reopen Visit button handler
                $(document).on('click', '.reopen-visit-btn', function() {
                    var iop = $(this).data('iop');
                    var patient = $(this).data('patient');
                    var name = $(this).data('name');
                    
                    $('#reopenPatientName').text(name + ' (' + iop + ')');
                    $('#reopenVisitLink').attr('href', '<?php echo base_url(); ?>app/opd/reopen_visit/' + iop + '/' + patient);
                    $('#reopenVisitModal').modal('show');
                });
                <?php endif; ?>
            });
        </script>
        <!-- END BDAY -->
        
        
    </body>
</html>