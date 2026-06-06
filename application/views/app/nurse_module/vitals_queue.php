<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Hebrew Medical Center - OPD Vitals Queue</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
</head>
<body class="skin-blue">
    <?php require_once(APPPATH.'views/include/header.php');?>

    <div class="wrapper row-offcanvas row-offcanvas-left">
        <?php require_once(APPPATH.'views/include/sidebar.php');?>

        <aside class="right-side">
            <section class="content-header">
                <h1>OPD Vitals Queue <small>Today's patients awaiting vitals</small></h1>
                <ol class="breadcrumb">
                    <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                    <li><a href="#">Nurse Module</a></li>
                    <li class="active">OPD Vitals Queue</li>
                </ol>
            </section>

            <section class="content">
                <?php if (isset($message) && $message != '') { echo $message; } ?>
				<?php
				$baseSegments = explode('/', trim(parse_url(current_url(), PHP_URL_PATH), '/'));
				$controller = isset($baseSegments[2]) ? $baseSegments[2] : 'nurse_module';
				$vitalsBase = base_url() . 'app/' . $controller;
				?>

                <!-- Summary boxes -->
                <div class="row">
                    <div class="col-lg-4 col-xs-6">
                        <div class="small-box bg-yellow">
                            <div class="inner">
                                <h3><?php echo isset($pending_count) ? $pending_count : 0; ?></h3>
                                <p>Pending Vitals</p>
                            </div>
                            <div class="icon"><i class="fa fa-clock-o"></i></div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-xs-6">
                        <div class="small-box bg-green">
                            <div class="inner">
                                <h3><?php echo isset($done_count) ? $done_count : 0; ?></h3>
                                <p>Vitals Completed</p>
                            </div>
                            <div class="icon"><i class="fa fa-check-circle"></i></div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-xs-6">
                        <div class="small-box bg-aqua">
                            <div class="inner">
                                <h3><?php echo (isset($pending_count) ? $pending_count : 0) + (isset($done_count) ? $done_count : 0); ?></h3>
                                <p>Total OPD Today</p>
                            </div>
                            <div class="icon"><i class="fa fa-users"></i></div>
                        </div>
                    </div>
                </div>

                <!-- Queue table -->
                <div class="row">
                    <div class="col-xs-12">
                        <div class="box box-primary">
                            <div class="box-header with-border">
                                <h3 class="box-title"><i class="fa fa-heartbeat"></i> OPD Vitals Queue &mdash; <?php echo date('d M Y'); ?></h3>
                                <div class="box-tools pull-right">
                                    <button class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button>
                                </div>
                            </div>
                            <div class="box-body table-responsive no-padding">
                                <table class="table table-hover table-striped">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>OPD No</th>
                                            <th>Patient</th>
                                            <th>Patient No</th>
                                            <th>Time</th>
                                            <th>Department</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php if (isset($vitals_queue) && count($vitals_queue) > 0) {
                                        $i = 1;
                                        foreach ($vitals_queue as $row) {
                                            $isDone = (isset($row->vitals_status) && $row->vitals_status === 'DONE');
                                    ?>
                                        <tr class="<?php echo $isDone ? 'success' : ''; ?>">
                                            <td><?php echo $i++; ?></td>
                                            <td><?php echo htmlspecialchars($row->IO_ID); ?></td>
                                            <td><?php echo htmlspecialchars($row->patient_name); ?></td>
                                            <td><?php echo htmlspecialchars($row->patient_no); ?></td>
                                            <td><?php echo htmlspecialchars($row->time_visit); ?></td>
                                            <td><?php echo htmlspecialchars(isset($row->dept_name) ? $row->dept_name : ''); ?></td>
                                            <td>
                                                <?php if ($isDone) { ?>
                                                    <span class="label label-success"><i class="fa fa-check"></i> Done</span>
                                                    <?php if (isset($row->vitals_at) && $row->vitals_at) { ?>
                                                        <br><small class="text-muted"><?php echo date('h:i A', strtotime($row->vitals_at)); ?></small>
                                                    <?php } ?>
                                                <?php } else { ?>
                                                    <span class="label label-warning"><i class="fa fa-clock-o"></i> Pending</span>
                                                <?php } ?>
                                            </td>
                                            <td>
                                                <?php if ($isDone) { ?>
                                                    <a href="<?php echo $vitalsBase.'/record_vitals/'.$row->IO_ID.'/'.$row->patient_no; ?>" class="btn btn-xs btn-default" title="Update Vitals">
                                                        <i class="fa fa-pencil"></i> Update
                                                    </a>
                                                <?php } else { ?>
                                                    <a href="<?php echo $vitalsBase.'/record_vitals/'.$row->IO_ID.'/'.$row->patient_no; ?>" class="btn btn-xs btn-primary" title="Record Vitals">
                                                        <i class="fa fa-heartbeat"></i> Record Vitals
                                                    </a>
                                                <?php } ?>
                                            </td>
                                        </tr>
                                    <?php } } else { ?>
                                        <tr><td colspan="8" class="text-center text-muted">No OPD patients registered today.</td></tr>
                                    <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

            </section>
        </aside>
    </div>

    <script src="<?php echo base_url();?>public/js/jquery.min.js"></script>
    <script src="<?php echo base_url();?>public/js/bootstrap.min.js" type="text/javascript"></script>
    <script src="<?php echo base_url();?>public/js/AdminLTE/app.js" type="text/javascript"></script>
</body>
</html>
