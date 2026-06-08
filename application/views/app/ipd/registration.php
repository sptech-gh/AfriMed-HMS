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
                    <h1>IPD Registration</h1>
                    <ol class="breadcrumb">
                        <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                        <li><a href="#">Patient Management</a></li>
                        <li><a href="<?php echo base_url()?>app/ipd/index">IPD</a></li>
                        <li class="active">IPD Registration</li>
                    </ol>
                </section>

                <!-- Main content -->
                <section class="content">

                <?php if (isset($message) && $message): echo $message; endif; ?>

                <?php if (!empty($admission_queue)): ?>
                <div class="row">
                    <div class="col-md-12">
                        <div class="box box-warning">
                            <div class="box-header with-border">
                                <h3 class="box-title"><i class="fa fa-hospital-o"></i> Pending Admissions from OPD <span class="badge bg-red"><?php echo count($admission_queue); ?></span></h3>
                            </div>
                            <div class="box-body table-responsive no-padding">
                                <div class="table-responsive">
                                <table class="table table-hover table-striped">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Patient No</th>
                                            <th>Patient Name</th>
                                            <th>OPD No</th>
                                            <th>Admission Reason</th>
                                            <th>Referring Doctor</th>
                                            <th>Queued At</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($admission_queue as $i => $aq): ?>
                                        <tr id="aq-row-<?php echo (int)$aq->queue_id; ?>">
                                            <td><?php echo $i + 1; ?></td>
                                            <td><?php echo anchor('app/patient/view/'.htmlspecialchars($aq->patient_no), htmlspecialchars($aq->patient_no)); ?></td>
                                            <td><?php echo htmlspecialchars($aq->patient_name); ?></td>
                                            <td><?php echo anchor('app/opd/view/'.htmlspecialchars($aq->iop_id).'/'.htmlspecialchars($aq->patient_no), htmlspecialchars($aq->iop_id)); ?></td>
                                            <td><?php echo htmlspecialchars($aq->admission_reason ?: '—'); ?></td>
                                            <td><?php echo htmlspecialchars($aq->doctor_name); ?></td>
                                            <td><?php echo date('M d H:i', strtotime($aq->created_at)); ?></td>
                                            <td>
                                                <?php echo anchor('app/ipd/admit/'.htmlspecialchars($aq->patient_no), '<i class="fa fa-bed"></i> Admit', array('class' => 'btn btn-xs btn-success', 'title' => 'Complete IPD Registration')); ?>
                                                <button class="btn btn-xs btn-default btn-aq-dismiss" data-queue-id="<?php echo (int)$aq->queue_id; ?>" title="Dismiss"><i class="fa fa-times"></i></button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                 <div class="row">
                     <div class="col-md-12">
                    
                         <div class="box">
                                 
                                 <div class="box-header">
                                    <h3 class="box-title">Search Patient to Masterlist</h3>
                                    
                                </div>
                            <div class="box-body table-responsive">
                                <form role="form" method="post" action="<?php echo base_url()?>app/ipd/admit_patient">
                                    <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                                        
                                        
                                        
                                
                                        <div class="form-group">
                                            <label for="exampleInputEmail1">Patient ID/LastName/FirstName</label>
                                            <input class="form-control input-sm" name="search" id="search" type="text" placeholder="Patient ID/LastName/FirstName" style="width: 350px;">
                                        </div>    
                                        

                                        
                                        <div class="form-group">
                                           
                                            <button class="btn btn-primary" name="btnSubmit" id="btnSubmit" type="submit"><i class="fa fa-search"></i> Search Patient</button>
                                        </div>
                                        <br>
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
        
        <!-- BDAY -->
         <script src="<?php echo base_url();?>public/datepicker/js/jquery-1.9.1.min.js"></script>
        <script src="<?php echo base_url();?>public/datepicker/js/bootstrap-datepicker.js"></script>
        <script type="text/javascript">
            var csrfName = '<?php echo $this->security->get_csrf_token_name(); ?>';
            var csrfHash = '<?php echo $this->security->get_csrf_hash(); ?>';
            $(document).ready(function () {
                $(document).on('click', '.btn-aq-dismiss', function () {
                    var btn = $(this);
                    var qid = btn.data('queue-id');
                    var dismissData = { queue_id: qid };
                    dismissData[csrfName] = csrfHash;
                    $.post('<?php echo base_url(); ?>app/ipd/mark_admitted_ajax', dismissData, function (res) {
                        if (res && res.ok) {
                            $('#aq-row-' + qid).fadeOut(300, function () { $(this).remove(); });
                        }
                    }, 'json');
                });
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