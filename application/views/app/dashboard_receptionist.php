<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo isset($companyInfo->site_title) ? $companyInfo->site_title : 'HMS'; ?> — Reception Dashboard</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />

    <style>
    /* ---- Reception Dashboard ---- */
    .content-header h1 { font-size: 20px; font-weight: 600; }
    .content-header h1 .header-date { font-size: 13px; font-weight: 400; color: #888; margin-left: 10px; }

    /* Stat boxes */
    .stat-row { margin-bottom: 5px; }
    .stat-row .small-box { border-radius: 4px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,0.1); margin-bottom: 15px; }
    .stat-row .small-box .inner { padding: 15px; }
    .stat-row .small-box .inner h3 { font-size: 32px; font-weight: 700; margin: 0 0 2px 0; }
    .stat-row .small-box .inner p { font-size: 13px; margin: 0; opacity: 0.9; }
    .stat-row .small-box .icon { font-size: 60px; top: 8px; right: 15px; opacity: 0.15; }
    .stat-row .small-box-footer { font-size: 12px; padding: 6px 15px; background: rgba(0,0,0,0.08); }
    .stat-row .small-box-footer:hover { background: rgba(0,0,0,0.15); }

    /* Section boxes */
    .rcpt-box { box-shadow: 0 1px 3px rgba(0,0,0,0.08); margin-bottom: 20px; border: none; }
    .rcpt-box .box-header { padding: 12px 15px; background: #fff; border-bottom: 1px solid #eee; }
    .rcpt-box .box-header .box-title { font-size: 14px; font-weight: 600; color: #333; }
    .rcpt-box .box-header .box-tools .btn-box-tool { color: #999; }
    .rcpt-box.box-primary { border-top: 3px solid #3c8dbc; }
    .rcpt-box.box-success { border-top: 3px solid #00a65a; }

    /* Tables */
    .rcpt-box .table { margin-bottom: 0; }
    .rcpt-box .table > thead > tr > th {
        background: #f5f6f8; font-size: 11px; font-weight: 700; text-transform: uppercase;
        letter-spacing: 0.4px; color: #555; padding: 10px 14px; border-bottom: 2px solid #ddd; white-space: nowrap;
    }
    .rcpt-box .table > tbody > tr > td { padding: 10px 14px; vertical-align: middle; font-size: 13px; }
    .rcpt-box .table > tbody > tr:hover > td { background-color: #f0f7fc; }
    .rcpt-box .table > tbody > tr:last-child > td { border-bottom: none; }

    /* Badges & buttons */
    .label { font-size: 11px; font-weight: 600; padding: 4px 8px; border-radius: 3px; }
    .btn-xs { font-size: 11px; padding: 4px 10px; border-radius: 3px; }

    /* Empty state */
    .empty-state { padding: 30px 15px; text-align: center; color: #aaa; }
    .empty-state i { font-size: 28px; display: block; margin-bottom: 8px; opacity: 0.4; }
    .empty-state span { font-size: 13px; }
    </style>
</head>
<body class="skin-blue">
    <?php require_once(APPPATH.'views/include/header.php');?>
    <div class="wrapper row-offcanvas row-offcanvas-left">
        <?php require_once(APPPATH.'views/include/sidebar.php');?>
        <aside class="right-side">
            <section class="content-header">
                <h1>Reception Dashboard <span class="header-date"><?php echo date('l, M d, Y'); ?></span></h1>
            </section>
            <section class="content">

				<?php
				$isNurse = false;
				if (isset($userInfo) && isset($userInfo->module)) {
					$mod = strtolower(trim((string)$userInfo->module));
					$isNurse = ($mod === 'nurse');
				}
				if (!$isNurse) {
					$isNurse = ((int)$this->session->userdata('user_role') === 7);
				}
				?>

                <!-- Stat Boxes -->
                <div class="row stat-row">
                    <div class="col-lg-3 col-xs-6">
                        <div class="small-box bg-aqua">
                            <div class="inner">
                                <h3><?php echo isset($today_registrations) ? (int)$today_registrations : 0; ?></h3>
                                <p>New Patients Today</p>
                            </div>
                            <div class="icon"><i class="ion ion-person-add"></i></div>
                            <a href="<?php echo base_url();?>app/patient" class="small-box-footer">Register Patient <i class="fa fa-arrow-circle-right"></i></a>
                        </div>
                    </div>
                    <div class="col-lg-3 col-xs-6">
                        <div class="small-box bg-green">
                            <div class="inner">
                                <h3><?php echo isset($today_opd) ? (int)$today_opd : 0; ?></h3>
                                <p>OPD Visits Today</p>
                            </div>
                            <div class="icon"><i class="ion ion-medkit"></i></div>
                            <a href="<?php echo base_url();?>app/opd" class="small-box-footer">View OPD <i class="fa fa-arrow-circle-right"></i></a>
                        </div>
                    </div>
                    <div class="col-lg-3 col-xs-6">
                        <div class="small-box bg-yellow">
                            <div class="inner">
                                <h3><?php echo isset($waiting_patients) ? (int)$waiting_patients : 0; ?></h3>
                                <p>Waiting for Doctor</p>
                            </div>
                            <div class="icon"><i class="fa fa-clock-o"></i></div>
                            <a href="<?php echo base_url();?>app/opd" class="small-box-footer">Manage Queue <i class="fa fa-arrow-circle-right"></i></a>
                        </div>
                    </div>
                    <div class="col-lg-3 col-xs-6">
                        <div class="small-box bg-purple">
                            <div class="inner">
                                <h3><?php echo isset($today_appointments) ? (int)$today_appointments : 0; ?></h3>
                                <p>Appointments Today</p>
                            </div>
                            <div class="icon"><i class="fa fa-calendar"></i></div>
                            <a href="<?php echo base_url();?>app/appointment" class="small-box-footer">View Appointments <i class="fa fa-arrow-circle-right"></i></a>
                        </div>
                    </div>
                </div>

                <!-- OPD Queue Table -->
                <div class="row">
                    <div class="col-lg-12">
                        <div class="box box-primary rcpt-box">
                            <div class="box-header">
                                <i class="fa fa-list-ol"></i>
                                <h3 class="box-title">Today's OPD Queue</h3>
                                <div class="box-tools pull-right">
                                    <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button>
                                </div>
                            </div>
                            <div class="box-body no-padding">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                    <thead><tr>
                                        <th>OPD No.</th><th>Patient No.</th><th>Patient Name</th><th>Department</th><th>Doctor</th><th>Time</th><th>Status</th>
                                    </tr></thead>
                                    <tbody>
                                    <?php
                                    $wf_labels = array(
                                        'REGISTERED'         => array('label' => 'Registered',          'class' => 'label-default'),
                                        'WAITING'            => array('label' => 'Waiting',              'class' => 'label-info'),
                                        'IN_CONSULTATION'    => array('label' => 'In Consultation',      'class' => 'label-warning'),
                                        'LAB_PENDING'        => array('label' => 'Lab Pending',          'class' => 'label-danger'),
                                        'LAB_COMPLETED'      => array('label' => 'Lab Done',             'class' => 'label-info'),
                                        'PHARMACY_PENDING'   => array('label' => 'Pharmacy Pending',     'class' => 'label-primary'),
                                        'PHARMACY_COMPLETED' => array('label' => 'Pharmacy Done',        'class' => 'label-info'),
                                        'CLINICALLY_CLEARED' => array('label' => 'Clinically Cleared',   'class' => 'label-success'),
                                        'BILLING_PENDING'    => array('label' => 'Billing Pending',      'class' => 'label-warning'),
                                        'FINAL_CLEARED'      => array('label' => 'Discharged',           'class' => 'label-default'),
                                        'COMPLETED'          => array('label' => 'Completed',            'class' => 'label-default'),
                                        'ADMITTED'           => array('label' => 'Admitted (IPD)',       'class' => 'label-danger'),
                                        'CANCELLED'          => array('label' => 'Cancelled',            'class' => 'label-default'),
                                        'REOPENED'           => array('label' => 'Reopened',             'class' => 'label-warning'),
                                    );
                                    if (isset($opd_queue) && is_array($opd_queue) && count($opd_queue) > 0) { foreach($opd_queue as $q) {
                                        $wfStatus = strtoupper(trim(isset($q->workflow_status) ? $q->workflow_status : 'WAITING'));
                                        $wfInfo = isset($wf_labels[$wfStatus]) ? $wf_labels[$wfStatus] : array('label' => $wfStatus, 'class' => 'label-default');
                                        $iop_safe = str_replace(' ', '-', $q->IO_ID);
                                        $pno_safe = str_replace(' ', '-', $q->patient_no);
                                    ?>
                                        <tr>
                                            <td><a href="<?php echo base_url();?>app/opd/view/<?php echo $iop_safe;?>/<?php echo $pno_safe;?>"><?php echo htmlspecialchars($q->IO_ID);?></a></td>
                                            <td><a href="<?php echo base_url();?>app/patient/view/<?php echo $pno_safe;?>"><?php echo htmlspecialchars($q->patient_no);?></a></td>
                                            <td><?php echo htmlspecialchars(trim(isset($q->patient_name) ? $q->patient_name : ''));?></td>
                                            <td><?php echo htmlspecialchars(isset($q->dept_name) ? $q->dept_name : '');?></td>
                                            <td><?php echo htmlspecialchars(trim(isset($q->doctor_name) ? $q->doctor_name : ''));?></td>
                                            <td><?php echo htmlspecialchars(isset($q->time_visit) ? $q->time_visit : '');?></td>
                                            <td><span class="label <?php echo $wfInfo['class'];?>"><?php echo htmlspecialchars($wfInfo['label']);?></span></td>
                                        </tr>
                                    <?php } } else { ?>
                                        <tr><td colspan="7"><div class="empty-state"><i class="fa fa-inbox"></i><span>No OPD visits today</span></div></td></tr>
                                    <?php } ?>
                                    </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Patient Registrations -->
                <div class="row">
                    <div class="col-lg-12">
                        <div class="box box-success rcpt-box">
                            <div class="box-header">
                                <i class="fa fa-user-plus"></i>
                                <h3 class="box-title">Today's New Patient Registrations</h3>
                                <div class="box-tools pull-right">
                                    <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button>
                                </div>
                            </div>
                            <div class="box-body no-padding">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                    <thead><tr>
                                        <th>Patient No.</th><th>Patient Name</th><th>Age</th><th>Gender</th><th>Registered</th><th>Vitals</th><th>Action</th>
                                    </tr></thead>
                                    <tbody>
                                    <?php if (isset($recent_registrations) && is_array($recent_registrations) && count($recent_registrations) > 0) { foreach($recent_registrations as $reg) { ?>
                                        <tr>
                                            <td><a href="<?php echo base_url();?>app/patient/view/<?php echo $reg->patient_no;?>"><?php echo $reg->patient_no;?></a></td>
                                            <td><?php echo isset($reg->patient_name) ? trim($reg->patient_name) : '';?></td>
                                            <td><?php echo isset($reg->age) ? $reg->age : '';?></td>
                                            <td><?php echo isset($reg->gender) ? $reg->gender : '';?></td>
                                            <td><?php echo isset($reg->date_entry) ? date("h:i A", strtotime($reg->date_entry)) : '';?></td>
                                            <td>
                                                <?php
                                                $vitalsStatus = isset($reg->today_vitals_status) ? strtoupper(trim((string)$reg->today_vitals_status)) : '';
                                                if ($vitalsStatus === 'DONE') {
                                                    echo '<span class="label label-success"><i class="fa fa-check"></i> Done</span>';
                                                } else {
                                                    echo '<span class="label label-warning"><i class="fa fa-clock-o"></i> Pending</span>';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <?php
                                                $todayIop    = isset($reg->today_iop_id) ? trim((string)$reg->today_iop_id) : '';
                                                $todayStatus = isset($reg->today_opd_status) ? strtoupper(trim((string)$reg->today_opd_status)) : '';
                                                if ($todayIop !== '') {
                                                    $iop_safe = str_replace(' ', '-', $todayIop);
                                                    $pno_safe = str_replace(' ', '-', $reg->patient_no);
                                                    $doneStatuses = array('FINAL_CLEARED','CLINICALLY_CLEARED','COMPLETED','CANCELLED','ADMITTED');
                                                    $vitalsPending = ($vitalsStatus !== 'DONE');
                                                    if (in_array($todayStatus, $doneStatuses, true)) {
                                                        echo '<a href="'.base_url().'app/opd/view/'.$iop_safe.'/'.$pno_safe.'" class="btn btn-xs btn-default"><i class="fa fa-eye"></i> View OPD</a>';
                                                    } else {
                                                        if ($vitalsPending) {
                                                            echo '<a href="'.base_url().'app/vitals/record_vitals/'.$iop_safe.'/'.$pno_safe.'" class="btn btn-xs btn-primary" style="margin-right:5px;"><i class="fa fa-heartbeat"></i> Record Vitals</a>';
                                                        }
                                                        echo '<a href="'.base_url().'app/opd/view/'.$iop_safe.'/'.$pno_safe.'" class="btn btn-xs btn-success"><i class="fa fa-stethoscope"></i> Continue OPD</a>';
                                                    }
                                                } else {
                                                    echo '<a href="'.base_url().'app/opd/start_opd_quick/'.htmlspecialchars($reg->patient_no).'" class="btn btn-xs btn-primary"><i class="fa fa-play"></i> Start OPD</a>';
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                    <?php } } else { ?>
                                        <tr><td colspan="7"><div class="empty-state"><i class="fa fa-user-plus"></i><span>No new patients registered today</span></div></td></tr>
                                    <?php } ?>
                                    </tbody>
                                    </table>
                                </div>
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
