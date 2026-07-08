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
                    <h1>
                        <i class="fa fa-flask"></i> Lab Queue
                        <?php if (isset($hasAccesstoAdmin) && $hasAccesstoAdmin && isset($orphaned_count) && (int)$orphaned_count > 0): ?>
                            <a href="<?php echo base_url(); ?>app/laboratory/orphaned_lab_requests" class="btn btn-xs btn-warning" style="margin-left:8px;">
                                <i class="fa fa-warning"></i> Orphaned Requests (<?php echo (int)$orphaned_count; ?>)
                            </a>
                        <?php endif; ?>
                    </h1>
                    <ol class="breadcrumb">
                        <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                        <li><a href="<?php echo base_url()?>app/laboratory">Laboratory</a></li>
                        <li class="active">Lab Queue</li>
                    </ol>
                </section>

                <section class="content">
                    <?php echo isset($message) && $message ? $message : ''; ?>

                    <!-- Cleared Patients Notification Banner -->
                    <?php if (isset($dispatch_notifications) && !empty($dispatch_notifications)): ?>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="box box-solid box-success">
                                <div class="box-header">
                                    <h3 class="box-title"><i class="fa fa-bell"></i> Cleared Patients — Awaiting Laboratory Services</h3>
                                </div>
                                <div class="box-body no-padding">
                                    <table class="table table-striped table-condensed table-hover">
                                        <thead>
                                            <tr>
                                                <th>Patient Name</th>
                                                <th>Patient ID</th>
                                                <th>Billed Items (Cleared)</th>
                                                <th>Cleared At</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($dispatch_notifications as $notif): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($notif->patient_name); ?></strong></td>
                                                <td><code><?php echo htmlspecialchars($notif->patient_no); ?></code></td>
                                                <td><?php echo htmlspecialchars($notif->item_details); ?></td>
                                                <td><?php echo date('M d, Y H:i', strtotime($notif->created_at)); ?></td>
                                                <td>
                                                    <form method="post" action="<?php echo base_url(); ?>app/cashier/mark_dispatched" style="display:inline;">
                                                        <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                                                        <input type="hidden" name="notification_id" value="<?php echo $notif->notification_id; ?>">
                                                        <input type="hidden" name="redirect_url" value="<?php echo current_url(); ?>">
                                                        <button type="submit" class="btn btn-xs btn-success"><i class="fa fa-check"></i> Process &amp; Mark Completed</button>
                                                    </form>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Summary boxes -->
                    <div class="row">
                        <div class="col-md-2 col-sm-4 col-xs-6">
                            <div class="small-box bg-aqua">
                                <div class="inner"><h3><?php echo isset($total_pending) ? $total_pending : 0; ?></h3><p>Pending Tests</p></div>
                                <div class="icon"><i class="fa fa-hourglass-half"></i></div>
                                <a href="<?php echo base_url();?>app/laboratory/lab_queue?status=PENDING" class="small-box-footer">View Pending <i class="fa fa-arrow-circle-right"></i></a>
                            </div>
                        </div>
                        <div class="col-md-2 col-sm-4 col-xs-6">
                            <div class="small-box bg-green">
                                <div class="inner"><h3><?php echo isset($total_completed) ? $total_completed : 0; ?></h3><p>Completed Tests</p></div>
                                <div class="icon"><i class="fa fa-check-circle"></i></div>
                                <a href="<?php echo base_url();?>app/laboratory/lab_queue?status=COMPLETED" class="small-box-footer">View Completed <i class="fa fa-arrow-circle-right"></i></a>
                            </div>
                        </div>
                        <div class="col-md-2 col-sm-4 col-xs-6">
                            <div class="small-box bg-red">
                                <div class="inner"><h3><?php echo isset($total_urgent) ? $total_urgent : 0; ?></h3><p>Urgent / STAT</p></div>
                                <div class="icon"><i class="fa fa-exclamation-triangle"></i></div>
                                <a href="<?php echo base_url();?>app/laboratory/lab_queue?status=URGENT" class="small-box-footer">View Urgent <i class="fa fa-arrow-circle-right"></i></a>
                            </div>
                        </div>
                        <div class="col-md-2 col-sm-4 col-xs-6">
                            <div class="small-box bg-yellow">
                                <div class="inner"><h3><?php echo isset($total_abnormal) ? $total_abnormal : 0; ?></h3><p>Abnormal Results (7d)</p></div>
                                <div class="icon"><i class="fa fa-warning"></i></div>
                                <a href="<?php echo base_url();?>app/laboratory/lab_queue?status=ALL" class="small-box-footer">View All <i class="fa fa-arrow-circle-right"></i></a>
                            </div>
                        </div>
                        <div class="col-md-2 col-sm-4 col-xs-6">
                            <div class="small-box bg-purple">
                                <div class="inner"><h3><?php echo isset($total_deferred) ? (int)$total_deferred : 0; ?></h3><p>Deferred / Unable</p></div>
                                <div class="icon"><i class="fa fa-calendar-times-o"></i></div>
                            </div>
                        </div>
                        <div class="col-md-2 col-sm-4 col-xs-6">
                            <div class="small-box bg-teal">
                                <div class="inner"><h3><?php echo isset($total_external) ? (int)$total_external : 0; ?></h3><p>External Labs</p></div>
                                <div class="icon"><i class="fa fa-external-link"></i></div>
                            </div>
                        </div>
                    </div>

                    <!-- Filter tabs -->
                    <?php $sf = isset($status_filter) ? $status_filter : 'PENDING'; ?>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="box">
                                <div class="box-header">
                                    <div class="btn-group">
                                        <a href="<?php echo base_url();?>app/laboratory/lab_queue?status=PENDING" class="btn btn-sm <?php echo $sf === 'PENDING' ? 'btn-primary' : 'btn-default'; ?>"><i class="fa fa-hourglass-half"></i> Pending</a>
                                        <a href="<?php echo base_url();?>app/laboratory/lab_queue?status=COMPLETED" class="btn btn-sm <?php echo $sf === 'COMPLETED' ? 'btn-primary' : 'btn-default'; ?>"><i class="fa fa-check"></i> Completed</a>
                                        <a href="<?php echo base_url();?>app/laboratory/lab_queue?status=ALL" class="btn btn-sm <?php echo ($sf !== 'PENDING' && $sf !== 'COMPLETED') ? 'btn-primary' : 'btn-default'; ?>"><i class="fa fa-list"></i> All</a>
                                    </div>
                                    <div class="box-tools pull-right">
                                        <button type="button" class="btn btn-sm btn-success" id="btnBatchResults" disabled data-toggle="modal" data-target="#batchResultsModal"><i class="fa fa-save"></i> Batch Save Results</button>
                                        <a href="<?php echo base_url();?>app/laboratory" class="btn btn-sm btn-default"><i class="fa fa-arrow-left"></i> Back to Lab</a>
                                    </div>
                                </div>

                                <div class="box-body table-responsive no-padding">
                                    <table class="table table-hover table-striped">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th><input type="checkbox" id="batchSelectAll"></th>
                                                <th>Test Name</th>
                                                <th>Patient ID</th>
                                                <th>Patient Name</th>
                                                <th>Age</th>
                                                <th>Priority</th>
                                                <th>Specimen</th>
                                                <th>Status</th>
                                                <th>Payment</th>
                                                <th>Technician</th>
                                                <th>Request Date</th>
                                                <th>TAT</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php if (isset($labs) && count($labs) > 0): ?>
                                            <?php foreach ($labs as $lab):
                                                $labId = (string)$lab->io_lab_id;
                                                $wf = isset($workflow_map[$labId]) ? $workflow_map[$labId] : null;
                                                $pay = isset($payment_map[$labId]) ? $payment_map[$labId] : null;
                                                $rawGate = (isset($raw_gate_map) && isset($raw_gate_map[$labId])) ? $raw_gate_map[$labId] : null;
                                                $paymentConfirmed = (is_array($rawGate) && isset($rawGate['allowed']) && (bool)$rawGate['allowed']);
                                                $wfStatus = $wf && isset($wf->status) ? strtoupper(trim((string)$wf->status)) : '';
                                                $reported = in_array($wfStatus, array('REPORTED_TEXT','REPORTED_PDF','REPORTED_BOTH','REPORTED'));
                                                $verified = ($wfStatus === 'VERIFIED');
                                                $hasResult = (isset($lab->result) && trim((string)$lab->result) !== '' && strtolower(trim((string)$lab->result)) !== 'uploaded');
                                                $hasUpload = (isset($lab->lab_result_upload) && trim((string)$lab->lab_result_upload) !== '');
                                                $labExtStatus = isset($lab->extended_status) ? strtoupper(trim((string)$lab->extended_status)) : '';
                                                $labExceptionStatuses = array('EXTERNAL_LAB','UNABLE_TO_PAY','DEFERRED','WAIVED','EMERGENCY','WAIVER_REQUESTED');
                                                $labIsException = in_array($labExtStatus, $labExceptionStatuses);
                                                
                                                // PAYMENT GATE: Determine if lab staff can access this test
                                                $isPaid = ($pay && isset($pay['paid']) && $pay['paid']) || $labIsException;
                                                $isAdmin = isset($hasAccesstoAdmin) && $hasAccesstoAdmin;
                                                $canAccessTest = $isPaid || $isAdmin || $hasResult || $hasUpload;

                                                // Status badge
                                                if ($wfStatus === 'VERIFIED') {
                                                    $statusBadge = '<span class="label label-primary"><i class="fa fa-check-circle"></i> Verified</span>';
                                                } else if (in_array($wfStatus, array('REPORTED_TEXT','REPORTED_PDF','REPORTED_BOTH','REPORTED'))) {
                                                    $statusBadge = '<span class="label label-success"><i class="fa fa-file-text"></i> Reported</span>';
                                                } else if ($wfStatus === 'IN_PROGRESS') {
                                                    $statusBadge = '<span class="label label-warning"><i class="fa fa-spinner"></i> In Progress</span>';
                                                } else if ($wfStatus === 'CANCELLED') {
                                                    $statusBadge = '<span class="label label-danger"><i class="fa fa-ban"></i> Cancelled</span>';
                                                } else if ($wfStatus === 'REQUESTED') {
                                                    $statusBadge = '<span class="label label-info"><i class="fa fa-clock-o"></i> Requested</span>';
                                                } else if ($labExtStatus === 'EXTERNAL_LAB') {
                                                    $statusBadge = '<span class="label label-info"><i class="fa fa-external-link"></i> External Lab</span>';
                                                } else if ($labExtStatus === 'EMERGENCY') {
                                                    $statusBadge = '<span class="label label-danger"><i class="fa fa-ambulance"></i> Emergency</span>';
                                                } else if ($labExtStatus === 'DEFERRED') {
                                                    $statusBadge = '<span class="label label-warning"><i class="fa fa-calendar"></i> Deferred</span>';
                                                } else if ($labExtStatus === 'UNABLE_TO_PAY') {
                                                    $statusBadge = '<span class="label label-warning"><i class="fa fa-exclamation-triangle"></i> Unable to Pay</span>';
                                                } else if ($labExtStatus === 'WAIVED') {
                                                    $statusBadge = '<span class="label label-success"><i class="fa fa-gift"></i> Waived</span>';
                                                } else if ($labExtStatus === 'WAIVER_REQUESTED') {
                                                    $statusBadge = '<span class="label label-info"><i class="fa fa-hourglass-half"></i> Waiver Pending</span>';
                                                } else {
                                                    $statusBadge = '<span class="label label-default"><i class="fa fa-clock-o"></i> New</span>';
                                                }

                                                // Payment badge
                                                $payLabel = $pay && isset($pay['label']) ? $pay['label'] : 'N/A';
                                                $payPaid = ($pay && isset($pay['paid']) ? $pay['paid'] : false) || $labIsException;
                                                if ($payPaid && !$labIsException) {
                                                    $payBadge = '<span class="label label-success">'.$payLabel.'</span>';
                                                } else if ($labExtStatus === 'EXTERNAL_LAB') {
                                                    $payBadge = '<span class="label label-info"><i class="fa fa-external-link"></i> External</span>';
                                                } else if ($labExtStatus === 'UNABLE_TO_PAY') {
                                                    $payBadge = '<span class="label label-warning"><i class="fa fa-exclamation-triangle"></i> Unable to Pay</span>';
                                                } else if ($labExtStatus === 'DEFERRED') {
                                                    $payBadge = '<span class="label label-warning"><i class="fa fa-calendar"></i> Deferred</span>';
                                                } else if ($labExtStatus === 'WAIVED') {
                                                    $payBadge = '<span class="label label-success"><i class="fa fa-gift"></i> Waived</span>';
                                                } else if ($labExtStatus === 'EMERGENCY') {
                                                    $payBadge = '<span class="label label-danger"><i class="fa fa-ambulance"></i> Emergency</span>';
                                                } else if (strpos($payLabel, 'Partial') !== false) {
                                                    $payBadge = '<span class="label label-warning">'.$payLabel.'</span>';
                                                } else if ($payLabel === 'No Invoice') {
                                                    $payBadge = '<span class="label label-default">'.$payLabel.'</span>';
                                                } else if ($payPaid) {
                                                    $payBadge = '<span class="label label-success">'.$payLabel.'</span>';
                                                } else {
                                                    $payBadge = '<span class="label label-danger">'.$payLabel.'</span>';
                                                }

                                                // Technician
                                                $techId = isset($lab->technician_id) ? trim((string)$lab->technician_id) : '';
                                                if ($techId === '' && $wf && isset($wf->technician_id)) {
                                                    $techId = trim((string)$wf->technician_id);
                                                }

                                                $testName = isset($lab->test_name) && $lab->test_name ? $lab->test_name : (isset($lab->laboratory_text) ? $lab->laboratory_text : 'Lab Test');
                                                $age = date('Y') - date('Y', strtotime($lab->birthday));
                                            ?>
                                            <?php
                                                $isUrgentRow = (isset($lab->is_urgent) && (int)$lab->is_urgent === 1);
                                                $rowClass = $isUrgentRow ? ' class="danger"' : '';
                                                $urgentBadge = $isUrgentRow ? '<span class="label label-danger"><i class="fa fa-exclamation-triangle"></i> URGENT</span>' : '<span class="label label-default">Routine</span>';
                                                $specimenDisplay = (isset($lab->specimen_type) && trim((string)$lab->specimen_type) !== '') ? htmlspecialchars((string)$lab->specimen_type) : '<span class="text-muted">-</span>';
                                                $canBatch = ($canAccessTest && !$verified && !$reported && $labExtStatus !== 'EXTERNAL_LAB');
                                            ?>
                                            <tr<?php echo $rowClass; ?>>
                                                <td><?php echo $lab->io_lab_id; ?></td>
                                                <td>
                                                    <?php if ($canBatch): ?>
                                                        <input type="checkbox" class="batch-select" data-io_lab_id="<?php echo (int)$lab->io_lab_id; ?>" data-iop_id="<?php echo htmlspecialchars(url_safe_id($lab->iop_id), ENT_QUOTES, 'UTF-8'); ?>" data-test_name="<?php echo htmlspecialchars((string)$testName, ENT_QUOTES, 'UTF-8'); ?>" data-patient_no="<?php echo htmlspecialchars((string)$lab->patient_no, ENT_QUOTES, 'UTF-8'); ?>">
                                                    <?php else: ?>
                                                        <input type="checkbox" disabled>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($canAccessTest): ?>
                                                        <a href="<?php echo base_url().'app/laboratory/results/'.$lab->io_lab_id.'/'.url_safe_id($lab->iop_id); ?>"><?php echo htmlspecialchars($testName); ?></a>
                                                    <?php else: ?>
                                                        <span class="text-muted" title="Payment required before processing"><?php echo htmlspecialchars($testName); ?></span>
                                                        <i class="fa fa-lock text-danger" title="Awaiting payment"></i>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo $lab->patient_no; ?></td>
                                                <td><?php echo $lab->patient_name; ?></td>
                                                <td><?php echo $age; ?></td>
                                                <td><?php echo $urgentBadge; ?></td>
                                                <td><?php echo $specimenDisplay; ?></td>
                                                <td><?php echo $statusBadge; ?></td>
                                                <td><?php echo $payBadge; ?></td>
                                                <td><?php echo $techId !== '' ? $techId : '<span class="text-muted">-</span>'; ?></td>
                                                <td><?php echo $lab->dDate; ?></td>
                                                <td><?php
                                                    $tatStart = (isset($lab->dDate) && $lab->dDate && $lab->dDate !== '0000-00-00 00:00:00') ? strtotime($lab->dDate) : 0;
                                                    $reportedAt = ($wf && isset($wf->reported_at) && $wf->reported_at && $wf->reported_at !== '0000-00-00 00:00:00') ? strtotime($wf->reported_at) : 0;
                                                    $tatEnd = $reportedAt > 0 ? $reportedAt : time();
                                                    if ($tatStart > 0) {
                                                        $tatMins = (int)round(($tatEnd - $tatStart) / 60);
                                                        if ($tatMins < 0) $tatMins = 0;
                                                        if ($tatMins < 60) {
                                                            $tatStr = $tatMins . 'm';
                                                        } else {
                                                            $tatHrs = floor($tatMins / 60);
                                                            $tatRemMins = $tatMins % 60;
                                                            $tatStr = $tatHrs . 'h' . ($tatRemMins > 0 ? ' ' . $tatRemMins . 'm' : '');
                                                        }
                                                        // Color: green <2h, orange 2-8h, red >8h; URGENT thresholds halved
                                                        $tatThreshWarn = $isUrgentRow ? 60  : 120;
                                                        $tatThreshCrit = $isUrgentRow ? 240 : 480;
                                                        if ($reportedAt > 0) {
                                                            $tatCls = 'label-default';
                                                        } else if ($tatMins <= $tatThreshWarn) {
                                                            $tatCls = 'label-success';
                                                        } else if ($tatMins <= $tatThreshCrit) {
                                                            $tatCls = 'label-warning';
                                                        } else {
                                                            $tatCls = 'label-danger';
                                                        }
                                                        echo '<span class="label ' . $tatCls . '" title="' . ($reportedAt > 0 ? 'Completed TAT' : 'Elapsed') . '">' . htmlspecialchars($tatStr, ENT_QUOTES, 'UTF-8') . '</span>';
                                                    } else {
                                                        echo '<span class="text-muted">-</span>';
                                                    }
                                                ?></td>
                                                <td>
                                                    <?php
                                                    if (!$verified && !$reported && $labExtStatus !== 'EXTERNAL_LAB'):
                                                        if ($canAccessTest): ?>
                                                            <a href="<?php echo base_url().'app/laboratory/results/'.$lab->io_lab_id.'/'.url_safe_id($lab->iop_id); ?>" class="btn btn-xs btn-primary" title="Enter Results"><i class="fa fa-pencil"></i> Enter</a>
                                                        <?php else: ?>
                                                            <span class="btn btn-xs btn-default disabled" title="Payment must be received before test can be processed" style="cursor:not-allowed;opacity:0.6;">
                                                                <i class="fa fa-lock"></i> Awaiting Payment
                                                            </span>
                                                        <?php endif;
                                                    endif; ?>
                                                    <?php if ($reported && !$verified): ?>
                                                        <a href="<?php echo base_url().'app/laboratory/mark_complete/'.$lab->io_lab_id; ?>" class="btn btn-xs btn-success" onclick="return confirm('Mark as completed/verified?')" title="Mark Complete"><i class="fa fa-check"></i> Complete</a>
                                                    <?php endif; ?>
                                                    <?php if ($verified): ?>
                                                        <span class="text-success"><i class="fa fa-check-circle"></i> Done</span>
                                                    <?php endif; ?>
                                                    <?php if ($hasUpload): ?>
                                                        <a href="<?php echo base_url().'app/laboratory/download_result/'.$lab->io_lab_id; ?>" class="btn btn-xs btn-default" title="View Upload"><i class="fa fa-download"></i></a>
                                                    <?php endif; ?>
                                                    <?php if ($labExtStatus === 'EXTERNAL_LAB'): ?>
                                                        <a href="<?php echo base_url().'app/laboratory/upload_external_result/'.$lab->io_lab_id; ?>" class="btn btn-xs btn-info" title="Upload External Result"><i class="fa fa-upload"></i> Upload Result</a>
                                                    <?php endif; ?>

                                                    <?php if (!$verified && $labExtStatus !== 'EXTERNAL_LAB' && $labExtStatus !== 'WAIVED' && !$paymentConfirmed): ?>
                                                    <div style="margin-top:3px;">
                                                        <button type="button" class="btn btn-info btn-xs" data-toggle="modal" data-target="#labExtModal<?php echo (int)$lab->io_lab_id; ?>"><i class="fa fa-external-link"></i> External</button>
                                                        <button type="button" class="btn btn-warning btn-xs" data-toggle="modal" data-target="#labUnableModal<?php echo (int)$lab->io_lab_id; ?>"><i class="fa fa-user-times"></i> Unable</button>
                                                        <button type="button" class="btn btn-default btn-xs" data-toggle="modal" data-target="#labDeferModal<?php echo (int)$lab->io_lab_id; ?>"><i class="fa fa-calendar"></i> Defer</button>
                                                        <button type="button" class="btn btn-danger btn-xs" data-toggle="modal" data-target="#labEmergModal<?php echo (int)$lab->io_lab_id; ?>"><i class="fa fa-ambulance"></i> Emerg</button>
                                                    </div>
                                                    <?php endif; ?>

                                                    <!-- Lab External Modal -->
                                                    <div class="modal fade" id="labExtModal<?php echo (int)$lab->io_lab_id; ?>" tabindex="-1" role="dialog">
                                                        <div class="modal-dialog"><div class="modal-content">
                                                            <form method="post" action="<?php echo base_url(); ?>app/laboratory/mark_lab_external">
                                                                <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                                                                <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title"><i class="fa fa-external-link"></i> External Lab Referral</h4></div>
                                                                <div class="modal-body">
                                                                    <input type="hidden" name="io_lab_id" value="<?php echo (int)$lab->io_lab_id; ?>">
                                                                    <p class="text-info">Patient will have this test done at an external laboratory. Test will be excluded from in-house billing.</p>
                                                                    <div class="form-group"><label>Referral Note</label><textarea class="form-control" name="reason" rows="2" placeholder="e.g. Refer to KATH lab, test unavailable here..."></textarea></div>
                                                                </div>
                                                                <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button><button type="submit" class="btn btn-info">Confirm External Referral</button></div>
                                                            </form>
                                                        </div></div>
                                                    </div>

                                                    <!-- Lab Unable to Pay Modal -->
                                                    <div class="modal fade" id="labUnableModal<?php echo (int)$lab->io_lab_id; ?>" tabindex="-1" role="dialog">
                                                        <div class="modal-dialog"><div class="modal-content">
                                                            <form method="post" action="<?php echo base_url(); ?>app/laboratory/mark_lab_unable_to_pay">
                                                                <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                                                                <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title"><i class="fa fa-user-times"></i> Unable to Pay</h4></div>
                                                                <div class="modal-body">
                                                                    <input type="hidden" name="io_lab_id" value="<?php echo (int)$lab->io_lab_id; ?>">
                                                                    <div class="alert alert-warning">Test proceeds on humanitarian grounds. Outstanding balance recorded for cashier follow-up.</div>
                                                                    <div class="form-group"><label>Reason</label><textarea class="form-control" name="reason" rows="2" placeholder="e.g. Patient indigent..."></textarea></div>
                                                                </div>
                                                                <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button><button type="submit" class="btn btn-warning">Confirm Unable to Pay</button></div>
                                                            </form>
                                                        </div></div>
                                                    </div>

                                                    <!-- Lab Deferred Modal -->
                                                    <div class="modal fade" id="labDeferModal<?php echo (int)$lab->io_lab_id; ?>" tabindex="-1" role="dialog">
                                                        <div class="modal-dialog"><div class="modal-content">
                                                            <form method="post" action="<?php echo base_url(); ?>app/laboratory/mark_lab_deferred">
                                                                <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                                                                <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title"><i class="fa fa-calendar"></i> Deferred Payment</h4></div>
                                                                <div class="modal-body">
                                                                    <input type="hidden" name="io_lab_id" value="<?php echo (int)$lab->io_lab_id; ?>">
                                                                    <p>Test proceeds now; payment deferred to a future date.</p>
                                                                    <div class="form-group"><label>Reason</label><textarea class="form-control" name="reason" rows="2" placeholder="e.g. Patient to pay on next visit..."></textarea></div>
                                                                    <div class="form-group"><label>Defer Until</label><input type="date" class="form-control" name="defer_until" min="<?php echo date('Y-m-d'); ?>"></div>
                                                                </div>
                                                                <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button><button type="submit" class="btn btn-default">Confirm Deferred</button></div>
                                                            </form>
                                                        </div></div>
                                                    </div>

                                                    <!-- Lab Emergency Modal -->
                                                    <div class="modal fade" id="labEmergModal<?php echo (int)$lab->io_lab_id; ?>" tabindex="-1" role="dialog">
                                                        <div class="modal-dialog"><div class="modal-content">
                                                            <form method="post" action="<?php echo base_url(); ?>app/laboratory/mark_lab_emergency">
                                                                <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                                                                <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title text-danger"><i class="fa fa-ambulance"></i> Emergency Override</h4></div>
                                                                <div class="modal-body">
                                                                    <input type="hidden" name="io_lab_id" value="<?php echo (int)$lab->io_lab_id; ?>">
                                                                    <div class="alert alert-danger"><strong>Emergency Override</strong> — Test proceeds without payment. Audit trail logged.</div>
                                                                    <div class="form-group"><label>Emergency Reason <span class="text-danger">*</span></label><textarea class="form-control" name="reason" rows="3" required></textarea></div>
                                                                </div>
                                                                <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button><button type="submit" class="btn btn-danger">Confirm Emergency</button></div>
                                                            </form>
                                                        </div></div>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr><td colspan="13" class="text-center text-muted">No lab tests found for this filter.</td></tr>
                                        <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="box-footer clearfix">
                                    <?php echo isset($pagination) ? $pagination : ''; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </aside>
        </div>

        <div class="modal fade" id="batchResultsModal" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        <h4 class="modal-title"><i class="fa fa-save"></i> Batch Save Results</h4>
                    </div>
                    <div class="modal-body">
                        <div id="batchResultsAlert"></div>
                        <form id="batchResultsForm">
                            <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                            <input type="hidden" name="iop_id" id="batchIopId" value="">
                            <div class="table-responsive">
                                <table class="table table-bordered" id="batchResultsTable">
                                    <thead>
                                        <tr>
                                            <th>Test</th>
                                            <th>Findings</th>
                                            <th>Result <span class="text-danger">*</span></th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </form>
                        <div class="text-muted">Only paid/accessible tests can be submitted in batch. Unpaid items will be skipped.</div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="btnSubmitBatch"><i class="fa fa-check"></i> Save Results</button>
                    </div>
                </div>
            </div>
        </div>

        <script src="<?php echo base_url();?>public/js/jquery.min.js"></script>
        <script src="<?php echo base_url();?>public/js/bootstrap.min.js" type="text/javascript"></script>
        <script src="<?php echo base_url();?>public/js/AdminLTE/app.js" type="text/javascript"></script>
        <script>
        (function(){
            var lockedIopId = null;

            function refreshBatchButton(){
                var n = $('.batch-select:checked').length;
                $('#btnBatchResults').prop('disabled', n === 0);
            }

            function enforceSingleVisit(){
                var checked = $('.batch-select:checked');
                if (checked.length === 0) {
                    lockedIopId = null;
                    $('.batch-select').prop('disabled', false);
                    $('input[type=checkbox][disabled]').not('.batch-select').prop('disabled', true);
                    return;
                }
                lockedIopId = $(checked[0]).data('iop_id');
                $('.batch-select').each(function(){
                    var iop = $(this).data('iop_id');
                    if (!$(this).is(':checked')) {
                        $(this).prop('disabled', (iop !== lockedIopId));
                    }
                });
            }

            $('#batchSelectAll').on('change', function(){
                var on = $(this).is(':checked');
                if (!on) {
                    $('.batch-select:checked').prop('checked', false);
                } else {
                    var first = $('.batch-select').filter(':not(:disabled)').first();
                    if (first.length) {
                        lockedIopId = first.data('iop_id');
                        $('.batch-select').each(function(){
                            if ($(this).data('iop_id') === lockedIopId && !$(this).is(':disabled')) {
                                $(this).prop('checked', true);
                            }
                        });
                    }
                }
                enforceSingleVisit();
                refreshBatchButton();
            });

            $(document).on('change', '.batch-select', function(){
                enforceSingleVisit();
                refreshBatchButton();
            });

            $('#batchResultsModal').on('show.bs.modal', function(){
                var checked = $('.batch-select:checked');
                var tbody = $('#batchResultsTable tbody');
                tbody.empty();
                $('#batchResultsAlert').html('');
                if (checked.length === 0) {
                    $('#batchIopId').val('');
                    return;
                }
                var iopId = $(checked[0]).data('iop_id');
                $('#batchIopId').val(iopId);
                checked.each(function(){
                    var ioLabId = $(this).data('io_lab_id');
                    var testName = $(this).data('test_name');
                    var row = '<tr>'+
                        '<td style="width:25%;"><strong>'+testName+'</strong><br><small class="text-muted">#'+ioLabId+'</small></td>'+
                        '<td style="width:35%;"><textarea class="form-control input-sm" rows="2" data-findings-for="'+ioLabId+'"></textarea></td>'+
                        '<td style="width:40%;"><textarea class="form-control input-sm" rows="2" data-result-for="'+ioLabId+'" required></textarea></td>'+
                        '</tr>';
                    tbody.append(row);
                });
            });

            $('#btnSubmitBatch').on('click', function(){
                var iopId = $('#batchIopId').val();
                var entries = [];
                $('#batchResultsTable tbody tr').each(function(){
                    var ioLabId = parseInt($(this).find('[data-result-for]').attr('data-result-for'), 10);
                    var findings = $(this).find('[data-findings-for="'+ioLabId+'"]').val();
                    var result = $(this).find('[data-result-for="'+ioLabId+'"]').val();
                    entries.push({io_lab_id: ioLabId, findings: findings, result: result});
                });

                var csrfName = $('#batchResultsForm input[type=hidden]').first().attr('name');
                var csrfVal = $('#batchResultsForm input[type=hidden]').first().val();

                $.ajax({
                    url: '<?php echo base_url(); ?>app/laboratory/batch_save_results',
                    method: 'POST',
                    dataType: 'json',
                    data: {
                        iop_id: iopId,
                        entries: JSON.stringify(entries),
                        [csrfName]: csrfVal,
                    }
                }).done(function(resp){
                    if (resp && resp.ok) {
                        $('#batchResultsAlert').html('<div class="alert alert-success">Saved: '+resp.saved+'. Skipped: '+(resp.skipped ? resp.skipped.length : 0)+'.</div>');
                        setTimeout(function(){ window.location.reload(); }, 800);
                    } else {
                        var msg = resp && resp.error ? resp.error : 'Batch save failed';
                        $('#batchResultsAlert').html('<div class="alert alert-danger">'+msg+'</div>');
                    }
                }).fail(function(){
                    $('#batchResultsAlert').html('<div class="alert alert-danger">Batch save failed</div>');
                });
            });

            refreshBatchButton();
        })();
        </script>
    </body>
</html>
