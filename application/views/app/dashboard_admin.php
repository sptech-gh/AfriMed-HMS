<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title><?php echo isset($companyInfo->site_title) ? $companyInfo->site_title : 'HMS'; ?> — Admin Dashboard</title>
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
                    <h1>Admin Dashboard <small>System Overview</small></h1>
                </section>
                <section class="content">

                    <!-- Summary Stat Boxes -->
                    <div class="row">
                        <div class="col-lg-3 col-xs-6">
                            <div class="small-box bg-aqua">
                                <div class="inner">
                                    <h3><?php echo isset($total_patients) ? $total_patients : 0; ?></h3>
                                    <p>Total Patients</p>
                                </div>
                                <div class="icon"><i class="ion ion-person-stalker"></i></div>
                                <a href="<?php echo base_url();?>app/patient" class="small-box-footer">View All <i class="fa fa-arrow-circle-right"></i></a>
                            </div>
                        </div>
                        <div class="col-lg-3 col-xs-6">
                            <div class="small-box bg-green">
                                <div class="inner">
                                    <h3><?php echo isset($today_opd) ? $today_opd : 0; ?></h3>
                                    <p>Today's OPD Visits</p>
                                </div>
                                <div class="icon"><i class="ion ion-medkit"></i></div>
                                <a href="<?php echo base_url();?>app/opd" class="small-box-footer">View OPD <i class="fa fa-arrow-circle-right"></i></a>
                            </div>
                        </div>
                        <div class="col-lg-3 col-xs-6">
                            <div class="small-box bg-yellow">
                                <div class="inner">
                                    <h3><?php echo isset($today_ipd) ? $today_ipd : 0; ?></h3>
                                    <p>Admitted (IPD)</p>
                                </div>
                                <div class="icon"><i class="ion ion-ios-bed"></i></div>
                                <a href="<?php echo base_url();?>app/ipd" class="small-box-footer">View IPD <i class="fa fa-arrow-circle-right"></i></a>
                            </div>
                        </div>
                        <div class="col-lg-3 col-xs-6">
                            <div class="small-box bg-red">
                                <div class="inner">
                                    <h3><?php echo isset($today_appointments) ? $today_appointments : 0; ?></h3>
                                    <p>Today's Appointments</p>
                                </div>
                                <div class="icon"><i class="ion ion-calendar"></i></div>
                                <a href="<?php echo base_url();?>app/appointment" class="small-box-footer">View All <i class="fa fa-arrow-circle-right"></i></a>
                            </div>
                        </div>
                    </div>

                    <!-- Revenue Row -->
                    <div class="row">
                        <div class="col-lg-4 col-xs-6">
                            <div class="info-box">
                                <span class="info-box-icon bg-aqua"><i class="fa fa-money"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Revenue Today</span>
                                    <span class="info-box-number"><?php echo number_format(isset($revenue_today) ? $revenue_today : 0, 2); ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4 col-xs-6">
                            <div class="info-box">
                                <span class="info-box-icon bg-green"><i class="fa fa-credit-card"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Payments Received</span>
                                    <span class="info-box-number"><?php echo number_format(isset($payments_today) ? $payments_today : 0, 2); ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4 col-xs-6">
                            <div class="info-box">
                                <span class="info-box-icon bg-yellow"><i class="fa fa-users"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Total Users</span>
                                    <span class="info-box-number"><?php echo isset($total_users) ? $total_users : 0; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Today's Appointments Table -->
                    <div class="row">
                        <section class="col-lg-12">
                            <div class="box box-primary">
                                <div class="box-header">
                                    <div class="pull-right box-tools">
                                        <button class="btn btn-primary btn-sm" data-widget='collapse' data-toggle="tooltip" title="Collapse"><i class="fa fa-minus"></i></button>
                                    </div>
                                    <i class="fa fa-calendar-check-o"></i>
                                    <h3 class="box-title">Today's Patient Appointments</h3>
                                </div>
                                <div class="box-body no-padding">
                                    <div class="table-responsive">
                                        <table class="table table-hover table-striped">
                                        <thead><tr>
                                            <th>Patient No.</th><th>Patient Name</th><th>Appointment Time</th><th>Doctor</th><th>Remarks</th>
                                        </tr></thead>
                                        <tbody>
                                        <?php if (isset($getTodayAppointment) && is_array($getTodayAppointment)) { foreach($getTodayAppointment as $apt) { ?>
                                            <tr>
                                                <td><a href="<?php echo base_url();?>app/patient/view/<?php echo $apt->patient_no;?>"><?php echo $apt->patient_no;?></a></td>
                                                <td><?php echo $apt->name;?></td>
                                                <td><?php echo date("M d, Y", strtotime($apt->appointmentDate))." ".$apt->appHour.":".$apt->appMinutes." ".$apt->appAMPM;?></td>
                                                <td><?php echo $apt->consultantDoctor;?></td>
                                                <td><?php echo $apt->appointmentReason;?></td>
                                            </tr>
                                        <?php } } ?>
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
                            <div class="box box-success">
                                <div class="box-header">
                                    <div class="pull-right box-tools">
                                        <button class="btn btn-success btn-sm" data-widget='collapse' data-toggle="tooltip" title="Collapse"><i class="fa fa-minus"></i></button>
                                    </div>
                                    <i class="fa fa-user-plus"></i>
                                    <h3 class="box-title">New Patients Today</h3>
                                </div>
                                <div class="box-body no-padding">
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                        <thead><tr><th>Patient No.</th><th>Name</th><th>Date</th><th>Age</th></tr></thead>
                                        <tbody>
                                        <?php if (isset($latest_patient) && is_array($latest_patient)) { foreach($latest_patient as $lp) { ?>
                                            <tr>
                                                <td><?php echo $lp->patient_no;?></td>
                                                <td><?php echo $lp->patient;?></td>
                                                <td><?php echo date("M d, Y h:i", strtotime($lp->date_entry2));?></td>
                                                <td><?php echo $lp->age;?></td>
                                            </tr>
                                        <?php } } ?>
                                        </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </section>
                        <section class="col-lg-6">
                            <div class="box box-info">
                                <div class="box-header">
                                    <div class="pull-right box-tools">
                                        <button class="btn btn-info btn-sm" data-widget='collapse' data-toggle="tooltip" title="Collapse"><i class="fa fa-minus"></i></button>
                                    </div>
                                    <i class="fa fa-stethoscope"></i>
                                    <h3 class="box-title">Visited Patients Today</h3>
                                </div>
                                <div class="box-body no-padding">
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                        <thead><tr><th>Visit ID</th><th>Name</th><th>Date</th><th>Department</th></tr></thead>
                                        <tbody>
                                        <?php if (isset($latest_visited_patient) && is_array($latest_visited_patient)) { foreach($latest_visited_patient as $vp) { ?>
                                            <tr>
                                                <td><?php echo $vp->IO_ID;?></td>
                                                <td><?php echo $vp->patient;?></td>
                                                <td><?php echo date("M d, Y", strtotime($vp->date_visit))." ".$vp->time_visit;?></td>
                                                <td><?php echo $vp->dept_name;?></td>
                                            </tr>
                                        <?php } } ?>
                                        </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>

                    <!-- Doctor Availability -->
                    <?php if(isset($hasAccesstoDoctorAvail) && $hasAccesstoDoctorAvail){?>
                    <div class="row">
                        <section class="col-lg-6">
                            <div class="box box-success">
                                <div class="box-header">
                                    <div class="pull-right box-tools">
                                        <button class="btn btn-success btn-sm" data-widget='collapse' data-toggle="tooltip" title="Collapse"><i class="fa fa-minus"></i></button>
                                    </div>
                                    <i class="fa fa-user-md"></i>
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
                            <div class="box box-danger">
                                <div class="box-header">
                                    <div class="pull-right box-tools">
                                        <button class="btn btn-danger btn-sm" data-widget='collapse' data-toggle="tooltip" title="Collapse"><i class="fa fa-minus"></i></button>
                                    </div>
                                    <i class="fa fa-user-md"></i>
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

                </section>
            </aside>
        </div>

        <script src="<?php echo base_url();?>public/js/jquery.min.js"></script>
        <script src="<?php echo base_url();?>public/js/bootstrap.min.js" type="text/javascript"></script>
        <script src="<?php echo base_url();?>public/js/AdminLTE/app.js" type="text/javascript"></script>
        <script type="text/javascript">
        var csrfName = '<?php echo $this->security->get_csrf_token_name(); ?>';
        var csrfHash = '<?php echo $this->security->get_csrf_hash(); ?>';
        $(document).ready(function(){
            <?php if(isset($hasAccesstoDoctorAvail) && $hasAccesstoDoctorAvail){?>
            doctorINF();
            doctorOUTF();
            <?php }?>
        });
        function doctorOUTF(){
            var postData = {}; postData[csrfName] = csrfHash;
            $.ajax({ url:"<?php echo base_url()?>general/getDoctorOUT", type:"POST", data:postData,
                success:function(r){ $('#doctorOUT').html(r); },
                beforeSend:function(){ $('#doctorOUT').html("<center><img src='../public/img/ajax-loader.gif'></center>"); }
            });
        }
        function doctorINF(){
            var postData = {}; postData[csrfName] = csrfHash;
            $.ajax({ url:"<?php echo base_url()?>general/getDoctorIN", type:"POST", data:postData,
                success:function(r){ $('#doctorIN').html(r); },
                beforeSend:function(){ $('#doctorIN').html("<center><img src='../public/img/ajax-loader.gif'></center>"); }
            });
        }
        function doctorProcess(id,status){
            if(confirm('Are you sure you want the doctor ' + status + '?')){
                var postData = {}; postData[csrfName] = csrfHash;
                $.ajax({ url:"<?php echo base_url()?>general/procDocAvail/"+id+"/"+status, type:"POST", data:postData,
                    success:function(){ alert('Doctor is '+status); doctorINF(); doctorOUTF(); },
                    beforeSend:function(){ $('#doctor'+status).html("<center><img src='../public/img/ajax-loader.gif'></center>"); }
                });
            }
        }
        </script>
    </body>
</html>
