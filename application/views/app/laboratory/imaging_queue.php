<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title>Hebrew Medical Center</title>
        <meta content="width=device-width, initial-scale=1.0" name="viewport">
        <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
        <link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
        <link href="<?php echo base_url();?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
        <link href="<?php echo base_url();?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
        <link href="<?php echo base_url();?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
        <!--[if lt IE 9]>
          <script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>
          <script src="https://oss.maxcdn.com/libs/respond.js/1.3.0/respond.min.js"></script>
        <![endif]-->
    </head>
    <body class="skin-blue">
        <?php require_once(APPPATH.'views/include/header.php');?>
        <div class="wrapper row-offcanvas row-offcanvas-left">
            <?php require_once(APPPATH.'views/include/sidebar.php');?>
            <aside class="right-side">
                <section class="content-header">
                    <h1><i class="fa fa-film"></i> <?php echo isset($page_title) ? $page_title : 'Imaging'; ?></h1>
                    <ol class="breadcrumb">
                        <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                        <li><a href="<?php echo base_url()?>app/laboratory">Laboratory</a></li>
                        <li class="active"><?php echo isset($page_title) ? $page_title : 'Imaging'; ?></li>
                    </ol>
                </section>

                <section class="content">
                    <?php echo isset($message) && $message ? $message : ''; ?>

                    <?php
                        $currentType = isset($imaging_type) ? $imaging_type : 'sonography';
                        $types = isset($imaging_types) ? $imaging_types : array();
                        $summary = isset($imaging_summary) ? $imaging_summary : array();
                    ?>

                    <!-- Imaging type summary cards -->
                    <div class="row">
                    <?php foreach ($summary as $tKey => $tInfo): ?>
                        <div class="col-md-4 col-sm-6 col-xs-12">
                            <div class="small-box <?php echo ($tKey === $currentType) ? 'bg-aqua' : 'bg-default'; ?>" style="<?php echo ($tKey !== $currentType) ? 'background:#f4f4f4;color:#333;' : ''; ?>">
                                <div class="inner">
                                    <h3><?php echo (int)$tInfo['pending']; ?></h3>
                                    <p><?php echo htmlspecialchars($tInfo['label']); ?> <small>(<?php echo (int)$tInfo['completed']; ?> completed)</small></p>
                                </div>
                                <div class="icon"><i class="fa <?php echo $tInfo['icon']; ?>"></i></div>
                                <a href="<?php echo base_url().'app/laboratory/imaging/'.$tKey; ?>" class="small-box-footer">
                                    <?php echo ($tKey === $currentType) ? '<strong>Current View</strong>' : 'View Queue'; ?> <i class="fa fa-arrow-circle-right"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    </div>

                    <!-- Type tabs -->
                    <div class="row">
                        <div class="col-md-12">
                            <div class="nav-tabs-custom">
                                <ul class="nav nav-tabs">
                                <?php foreach ($types as $tKey => $tInfo): ?>
                                    <li class="<?php echo ($tKey === $currentType) ? 'active' : ''; ?>">
                                        <a href="<?php echo base_url().'app/laboratory/imaging/'.$tKey; ?>">
                                            <i class="fa <?php echo $tInfo['icon']; ?>"></i> <?php echo $tInfo['label']; ?>
                                            <?php
                                                $pCount = isset($summary[$tKey]) ? (int)$summary[$tKey]['pending'] : 0;
                                                if ($pCount > 0): ?>
                                                <span class="badge bg-red"><?php echo $pCount; ?></span>
                                            <?php endif; ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                                </ul>

                                <div class="tab-content">
                                    <div class="tab-pane active">

                                        <!-- Status filter -->
                                        <?php $sf = isset($status_filter) ? $status_filter : 'PENDING'; ?>
                                        <div style="margin-bottom:15px;">
                                            <div class="btn-group">
                                                <a href="<?php echo base_url().'app/laboratory/imaging/'.$currentType.'?status=PENDING'; ?>" class="btn btn-sm <?php echo $sf === 'PENDING' ? 'btn-primary' : 'btn-default'; ?>"><i class="fa fa-hourglass-half"></i> Pending</a>
                                                <a href="<?php echo base_url().'app/laboratory/imaging/'.$currentType.'?status=COMPLETED'; ?>" class="btn btn-sm <?php echo $sf === 'COMPLETED' ? 'btn-primary' : 'btn-default'; ?>"><i class="fa fa-check"></i> Completed</a>
                                                <a href="<?php echo base_url().'app/laboratory/imaging/'.$currentType.'?status=ALL'; ?>" class="btn btn-sm <?php echo ($sf !== 'PENDING' && $sf !== 'COMPLETED') ? 'btn-primary' : 'btn-default'; ?>"><i class="fa fa-list"></i> All</a>
                                            </div>
                                            <div class="pull-right">
                                                <a href="<?php echo base_url();?>app/laboratory" class="btn btn-sm btn-default"><i class="fa fa-arrow-left"></i> Back to Lab</a>
                                            </div>
                                        </div>

                                        <!-- Results table -->
                                        <div class="table-responsive">
                                            <table class="table table-hover table-striped">
                                                <thead>
                                                    <tr>
                                                        <th>#</th>
                                                        <th>Test / Scan</th>
                                                        <th>Patient ID</th>
                                                        <th>Patient Name</th>
                                                        <th>Age</th>
                                                        <th>Status</th>
                                                        <th>Payment</th>
                                                        <th>Technician</th>
                                                        <th>Request Date</th>
                                                        <th>Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                <?php if (isset($labs) && count($labs) > 0): ?>
                                                    <?php foreach ($labs as $lab):
                                                        $labId = (string)$lab->io_lab_id;
                                                        $wf = isset($workflow_map[$labId]) ? $workflow_map[$labId] : null;
                                                        $pay = isset($payment_map[$labId]) ? $payment_map[$labId] : null;
                                                        $wfStatus = $wf && isset($wf->status) ? strtoupper(trim((string)$wf->status)) : '';
                                                        $hasResult = (isset($lab->result) && trim((string)$lab->result) !== '' && strtolower(trim((string)$lab->result)) !== 'uploaded');
                                                        $hasUpload = (isset($lab->lab_result_upload) && trim((string)$lab->lab_result_upload) !== '');

                                                        // Status badge
                                                        if ($wfStatus === 'VERIFIED') {
                                                            $statusBadge = '<span class="label label-primary"><i class="fa fa-check-circle"></i> Verified</span>';
                                                        } else if (in_array($wfStatus, array('REPORTED_TEXT','REPORTED_PDF','REPORTED_BOTH','REPORTED'))) {
                                                            $statusBadge = '<span class="label label-success"><i class="fa fa-file-text"></i> Reported</span>';
                                                        } else if ($wfStatus === 'IN_PROGRESS') {
                                                            $statusBadge = '<span class="label label-warning"><i class="fa fa-spinner"></i> In Progress</span>';
                                                        } else if ($wfStatus === 'CANCELLED') {
                                                            $statusBadge = '<span class="label label-danger"><i class="fa fa-ban"></i> Cancelled</span>';
                                                        } else if ($wfStatus === 'REQUESTED' || $wfStatus === 'SCHEDULED') {
                                                            $statusBadge = '<span class="label label-info"><i class="fa fa-clock-o"></i> '.ucfirst(strtolower($wfStatus)).'</span>';
                                                        } else if ($wfStatus === 'PERFORMED') {
                                                            $statusBadge = '<span class="label label-info"><i class="fa fa-stethoscope"></i> Performed</span>';
                                                        } else {
                                                            $statusBadge = '<span class="label label-default"><i class="fa fa-clock-o"></i> New</span>';
                                                        }

                                                        // Payment badge
                                                        $payLabel = $pay && isset($pay['label']) ? $pay['label'] : 'N/A';
                                                        $payPaid = $pay && isset($pay['paid']) ? $pay['paid'] : false;
                                                        if ($payPaid) {
                                                            $payBadge = '<span class="label label-success">'.$payLabel.'</span>';
                                                        } else if (strpos($payLabel, 'Partial') !== false) {
                                                            $payBadge = '<span class="label label-warning">'.$payLabel.'</span>';
                                                        } else if ($payLabel === 'No Invoice') {
                                                            $payBadge = '<span class="label label-default">'.$payLabel.'</span>';
                                                        } else {
                                                            $payBadge = '<span class="label label-danger">'.$payLabel.'</span>';
                                                        }

                                                        $techId = $wf && isset($wf->technician_id) ? trim((string)$wf->technician_id) : '';
                                                        $testName = isset($lab->test_name) && $lab->test_name ? $lab->test_name : (isset($lab->laboratory_text) ? $lab->laboratory_text : 'Imaging Test');
                                                        $age = date('Y') - date('Y', strtotime($lab->birthday));
                                                        
                                                        // PAYMENT GATE: Determine if staff can access this test
                                                        $isAdmin = isset($hasAccesstoAdmin) && $hasAccesstoAdmin;
                                                        $canAccessTest = $payPaid || $isAdmin || $hasResult || $hasUpload || $payLabel === 'No Invoice';
                                                    ?>
                                                    <tr>
                                                        <td><?php echo $lab->io_lab_id; ?></td>
                                                        <td>
                                                            <?php if ($canAccessTest): ?>
                                                                <a href="<?php echo base_url().'app/laboratory/results/'.$lab->io_lab_id.'/'.url_safe_id($lab->iop_id); ?>">
                                                                    <?php echo htmlspecialchars($testName); ?>
                                                                </a>
                                                            <?php else: ?>
                                                                <span class="text-muted" title="Payment required before processing"><?php echo htmlspecialchars($testName); ?></span>
                                                                <i class="fa fa-lock text-danger" title="Awaiting payment"></i>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?php echo $lab->patient_no; ?></td>
                                                        <td><?php echo $lab->patient_name; ?></td>
                                                        <td><?php echo $age; ?></td>
                                                        <td><?php echo $statusBadge; ?></td>
                                                        <td><?php echo $payBadge; ?></td>
                                                        <td><?php echo $techId !== '' ? $techId : '<span class="text-muted">-</span>'; ?></td>
                                                        <td><?php echo $lab->dDate; ?></td>
                                                        <td>
                                                            <?php
                                                            $reported = in_array($wfStatus, array('REPORTED_TEXT','REPORTED_PDF','REPORTED_BOTH','REPORTED'));
                                                            $verified = ($wfStatus === 'VERIFIED');
                                                            ?>
                                                            <?php if (!$verified && !$reported): ?>
                                                                <?php if ($canAccessTest): ?>
                                                                    <a href="<?php echo base_url().'app/laboratory/results/'.$lab->io_lab_id.'/'.url_safe_id($lab->iop_id); ?>" class="btn btn-xs btn-primary" title="Enter/Upload Results"><i class="fa fa-pencil"></i> Enter</a>
                                                                <?php else: ?>
                                                                    <span class="btn btn-xs btn-default disabled" title="Payment must be received before test can be processed" style="cursor:not-allowed;opacity:0.6;">
                                                                        <i class="fa fa-lock"></i> Awaiting Payment
                                                                    </span>
                                                                <?php endif; ?>
                                                            <?php endif; ?>
                                                            <?php if ($reported && !$verified): ?>
                                                                <a href="<?php echo base_url().'app/laboratory/mark_complete/'.$lab->io_lab_id.'?return=imaging/'.$currentType; ?>" class="btn btn-xs btn-success" onclick="return confirm('Mark as completed/verified?')" title="Mark Complete"><i class="fa fa-check"></i> Complete</a>
                                                            <?php endif; ?>
                                                            <?php if ($verified): ?>
                                                                <span class="text-success"><i class="fa fa-check-circle"></i> Done</span>
                                                            <?php endif; ?>
                                                            <?php if ($hasUpload): ?>
                                                                <a href="<?php echo base_url().'app/laboratory/download_result/'.$lab->io_lab_id; ?>" class="btn btn-xs btn-default" title="View/Download"><i class="fa fa-download"></i></a>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <tr><td colspan="10" class="text-center text-muted">No imaging requests found for this filter.</td></tr>
                                                <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>

                                        <div class="clearfix">
                                            <?php echo isset($pagination) ? $pagination : ''; ?>
                                        </div>
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
