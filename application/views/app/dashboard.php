<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title>Hebrew Medical Center — Dashboard</title>
        <meta content="width=device-width, initial-scale=1.0" name="viewport">
        <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">

        <link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
        <link href="<?php echo base_url();?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
        <link href="<?php echo base_url();?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
        <link href="<?php echo base_url();?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
        <link href="<?php echo base_url();?>public/css/datatables/dataTables.bootstrap.css" rel="stylesheet" type="text/css" />
    </head>  
    
    <body class="skin-blue">
        <?php require_once(APPPATH.'views/include/header.php');?>
        
        <div class="wrapper row-offcanvas row-offcanvas-left">
            
            <?php require_once(APPPATH.'views/include/sidebar.php');?>

            <aside class="right-side">                
                <section class="content-header">
                    <h1>Dashboard <small>Overview</small></h1>
                </section>

                <section class="content">

                <!-- Summary Stats Row -->
                <div class="row" style="margin-bottom:10px;">
                    <div class="col-md-3 col-sm-6">
                        <div class="dashboard-stat stat-primary">
                            <div class="dashboard-stat-value"><?php echo is_array($getTodayAppointment) ? count($getTodayAppointment) : 0; ?></div>
                            <div class="dashboard-stat-label">Today's Appointments</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="dashboard-stat stat-success">
                            <div class="dashboard-stat-value"><?php echo is_array($latest_patient) ? count($latest_patient) : 0; ?></div>
                            <div class="dashboard-stat-label">New Patients</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="dashboard-stat stat-info">
                            <div class="dashboard-stat-value"><?php echo is_array($latest_visited_patient) ? count($latest_visited_patient) : 0; ?></div>
                            <div class="dashboard-stat-label">Visited Patients</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="dashboard-stat stat-warning">
                            <div class="dashboard-stat-value"><i class="fa fa-calendar"></i></div>
                            <div class="dashboard-stat-label"><?php echo date('M d, Y'); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Today's Appointments -->
                <div class="row">
                    <section class="col-lg-12">
                        <div class="box box-primary">
                            <div class="box-header">
                                <div class="pull-right box-tools">
                                    <button class="btn btn-primary btn-sm" data-widget='collapse' data-toggle="tooltip" title="Collapse"><i class="fa fa-minus"></i></button>
                                </div>
                                <i class="fa fa-calendar-check-o" style="color:var(--hms-primary);margin-right:8px;"></i>
                                <h3 class="box-title">Today's Patient Appointments</h3>
                            </div>
                            <div class="box-body no-padding">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Patient No.</th>
                                            <th>Patient Name</th>
                                            <th>Appointment Date</th>
                                            <th>Consultant Doctor</th>
                                            <th>Entry Date</th>
                                            <th>Remarks</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($getTodayAppointment as $getTodayAppointment){?>
                                        <tr>
                                            <td><a href="patient/view/<?php echo $getTodayAppointment->patient_no?>"><?php echo $getTodayAppointment->patient_no?></a></td>
                                            <td><?php echo $getTodayAppointment->name?></td>
                                            <td><?php echo date("M d, Y", strtotime($getTodayAppointment->appointmentDate))." ".$getTodayAppointment->appHour.":".$getTodayAppointment->appMinutes." ".$getTodayAppointment->appAMPM;?></td>
                                            <td><?php echo $getTodayAppointment->consultantDoctor?></td>
                                            <td><?php echo date("M d, Y", strtotime($getTodayAppointment->dateEntry));?></td>
                                            <td><?php echo $getTodayAppointment->appointmentReason?></td>
                                        </tr>
                                        <?php }?>
                                    </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <!-- New Patients + Visited Patients -->
                <div class="row">
                    <section class="col-lg-6">
                        <div class="box box-primary">
                            <div class="box-header">
                                <div class="pull-right box-tools">
                                    <button class="btn btn-primary btn-sm" data-widget='collapse' data-toggle="tooltip" title="Collapse"><i class="fa fa-minus"></i></button>
                                </div>
                                <i class="fa fa-user-plus" style="color:var(--hms-success);margin-right:8px;"></i>
                                <h3 class="box-title">New Patients</h3>
                            </div>
                            <div class="box-body no-padding">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Patient No.</th>
                                            <th>Patient Name</th>
                                            <th>Date</th>
                                            <th>Age</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($latest_patient as $latest_patient){?>
                                        <tr>
                                            <td><?php echo $latest_patient->patient_no?></td>
                                            <td><?php echo $latest_patient->patient?></td>
                                            <td><?php echo date("M d, Y h:i:s", strtotime($latest_patient->date_entry2));?></td>
                                            <td><?php echo $latest_patient->age?></td>
                                        </tr>
                                        <?php }?>
                                    </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </section>
                    
                    <section class="col-lg-6">
                        <div class="box box-primary">
                            <div class="box-header">
                                <div class="pull-right box-tools">
                                    <button class="btn btn-primary btn-sm" data-widget='collapse' data-toggle="tooltip" title="Collapse"><i class="fa fa-minus"></i></button>
                                </div>
                                <i class="fa fa-stethoscope" style="color:var(--hms-info);margin-right:8px;"></i>
                                <h3 class="box-title">Visited Patients</h3>
                            </div>
                            <div class="box-body no-padding">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>OPD No.</th>
                                            <th>Patient Name</th>
                                            <th>Date</th>
                                            <th>Department</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($latest_visited_patient as $latest_visited_patient){?>
                                        <tr>
                                            <td><?php echo $latest_visited_patient->IO_ID?></td>
                                            <td><?php echo $latest_visited_patient->patient?></td>
                                            <td><?php echo date("M d, Y", strtotime($latest_visited_patient->date_visit))." ".$latest_visited_patient->time_visit;?></td>
                                            <td><?php echo $latest_visited_patient->dept_name?></td>
                                        </tr>
                                        <?php }?>
                                    </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <!-- Doctor Availability -->
                <?php if($hasAccesstoDoctorAvail){?>
                <div class="row">
                    <section class="col-lg-6">
                        <div class="box box-primary">
                            <div class="box-header">
                                <div class="pull-right box-tools">
                                    <button class="btn btn-primary btn-sm" data-widget='collapse' data-toggle="tooltip" title="Collapse"><i class="fa fa-minus"></i></button>
                                </div>
                                <i class="fa fa-user-md" style="color:var(--hms-success);margin-right:8px;"></i>
                                <h3 class="box-title">Doctors IN</h3>
                            </div>
                            <div class="box-body no-padding">
                                <div class="table-responsive" style="max-height:350px; overflow-y:auto;">
                                    <div id="doctorIN"></div>
                                </div>
                            </div>
                        </div>
                    </section>
                    
                    <section class="col-lg-6">
                        <div class="box box-primary">
                            <div class="box-header">
                                <div class="pull-right box-tools">
                                    <button class="btn btn-primary btn-sm" data-widget='collapse' data-toggle="tooltip" title="Collapse"><i class="fa fa-minus"></i></button>
                                </div>
                                <i class="fa fa-user-md" style="color:var(--hms-danger);margin-right:8px;"></i>
                                <h3 class="box-title">Doctors OUT</h3>
                            </div>
                            <div class="box-body no-padding">
                                <div class="table-responsive" style="max-height:350px; overflow-y:auto;">
                                    <div id="doctorOUT"></div>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
                <?php }?>

                </section><!-- /.content -->


            </aside><!-- /.right-side -->
        </div><!-- ./wrapper -->
  
        
         <script src="<?php echo base_url();?>public/js/jquery.min.js"></script>
         <script src="<?php echo base_url();?>public/js/bootstrap.min.js" type="text/javascript"></script>     
         <script src="<?php echo base_url();?>public/js/AdminLTE/app.js" type="text/javascript"></script>

         <script type="text/javascript">
         var csrfName = '<?php echo $this->security->get_csrf_token_name(); ?>';
         var csrfHash = '<?php echo $this->security->get_csrf_hash(); ?>';
         $(document).ready(function(){
            
            doctorOUTF();
            doctorINF();

         });

         function doctorOUTF()
         {
            var postData = {};
            postData[csrfName] = csrfHash;
            $.ajax({
                url: "<?php echo base_url()?>general/getDoctorOUT",
                type: "POST",
                data: postData,
                success: function(result){
                    $('#doctorOUT').html(result);
                },beforeSend: function(){
                    $('#doctorOUT').html("<center><img src='../public/img/ajax-loader.gif'></center>");
                }
            });
         }

         function doctorINF()
         {
            var postData = {};
            postData[csrfName] = csrfHash;
            $.ajax({
                url: "<?php echo base_url()?>general/getDoctorIN",
                type: "POST",
                data: postData,
                success: function(result){
                    $('#doctorIN').html(result);
                },beforeSend: function(){
                    $('#doctorIN').html("<center><img src='../public/img/ajax-loader.gif'></center>");
                }
            });
         }

         function doctorProcess(id,status)
         {
            if(confirm('Are you sure you want the doctor ' + status + '?'))
            {
                var postData = {};
                postData[csrfName] = csrfHash;
                $.ajax({
                    url: "<?php echo base_url()?>general/procDocAvail/" + id + "/" + status,
                    type: "POST",
                    data: postData,
                    success: function()
                    {
                        alert('Doctor is ' + status);
                        doctorINF()
                        doctorOUTF()
                    },
                    beforeSend: function(){
                        $('#doctor' + status).html("<center><img src='../public/img/ajax-loader.gif'></center>");
                    }
                });
                return true;
            }
            else
            {
                return false;
            }

         }
         </script>
         
    </body>
</html>