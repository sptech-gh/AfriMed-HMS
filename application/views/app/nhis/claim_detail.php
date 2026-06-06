<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Claim Details - Hebrew Medical Center</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">

    <link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />

    <style>
        .claim-status { font-size: 14px; padding: 6px 12px; }
        .detail-label { font-weight: bold; color: #666; }
        .item-type { font-size: 10px; padding: 3px 6px; }
    </style>
</head>
<body class="skin-blue">
    <?php require_once(APPPATH.'views/include/header.php');?>

    <div class="wrapper row-offcanvas row-offcanvas-left">
        <?php require_once(APPPATH.'views/include/sidebar.php');?>

        <aside class="right-side">
            <section class="content-header">
                <h1>
                    Claim: <?php echo isset($claim) ? $claim->claim_number : ''; ?>
                    <?php if (isset($claim)): ?>
                        <?php
                            $statusClass = 'default';
                            switch ($claim->claim_status) {
                                case 'approved': $statusClass = 'success'; break;
                                case 'rejected': $statusClass = 'danger'; break;
                                case 'submitted': $statusClass = 'info'; break;
                                case 'pending': case 'draft': $statusClass = 'warning'; break;
                                case 'paid': $statusClass = 'primary'; break;
                            }
                        ?>
                        <span class="label label-<?php echo $statusClass; ?> claim-status">
                            <?php echo strtoupper($claim->claim_status); ?>
                        </span>
                    <?php endif; ?>
                </h1>
                <ol class="breadcrumb">
                    <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                    <li><a href="<?php echo base_url()?>app/nhis">NHIS</a></li>
                    <li><a href="<?php echo base_url()?>app/nhis/claims">Claims</a></li>
                    <li class="active">Details</li>
                </ol>
            </section>

            <section class="content">
                <?php if ($this->session->flashdata('success')): ?>
                    <div class="alert alert-success alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                        <?php echo $this->session->flashdata('success'); ?>
                    </div>
                <?php endif; ?>
                <?php if ($this->session->flashdata('error')): ?>
                    <div class="alert alert-danger alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                        <?php echo $this->session->flashdata('error'); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($claim)): ?>
                <div class="row">
                    <!-- Claim Info -->
                    <div class="col-md-8">
                        <div class="box box-primary">
                            <div class="box-header with-border">
                                <h3 class="box-title"><i class="fa fa-file-text"></i> Claim Information</h3>
                            </div>
                            <div class="box-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <table class="table table-condensed">
                                            <tr>
                                                <td class="detail-label">Claim Number</td>
                                                <td><strong><?php echo $claim->claim_number; ?></strong></td>
                                            </tr>
                                            <tr>
                                                <td class="detail-label">Patient</td>
                                                <td>
                                                    <?php echo $claim->firstname . ' ' . $claim->lastname; ?>
                                                    <br><small class="text-muted"><?php echo $claim->patient_no; ?></small>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="detail-label">NHIS Member ID</td>
                                                <td><?php echo $claim->nhis_member_id; ?></td>
                                            </tr>
                                            <tr>
                                                <td class="detail-label">Encounter Type</td>
                                                <td>
                                                    <span class="label label-<?php echo $claim->encounter_type == 'IPD' ? 'info' : 'default'; ?>">
                                                        <?php echo $claim->encounter_type; ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="detail-label">Visit Date</td>
                                                <td><?php echo date('d M Y', strtotime($claim->visit_date)); ?></td>
                                            </tr>
                                            <?php if ($claim->discharge_date): ?>
                                            <tr>
                                                <td class="detail-label">Discharge Date</td>
                                                <td><?php echo date('d M Y', strtotime($claim->discharge_date)); ?></td>
                                            </tr>
                                            <?php endif; ?>
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <table class="table table-condensed">
                                            <tr>
                                                <td class="detail-label">Attending Doctor</td>
                                                <td><?php echo $claim->attending_doctor ?: '-'; ?></td>
                                            </tr>
                                            <tr>
                                                <td class="detail-label">Facility Code</td>
                                                <td><?php echo $claim->facility_code; ?></td>
                                            </tr>
                                            <tr>
                                                <td class="detail-label">NHIS Reference</td>
                                                <td><?php echo $claim->nhis_reference_id ?: '-'; ?></td>
                                            </tr>
                                            <tr>
                                                <td class="detail-label">Submitted At</td>
                                                <td><?php echo $claim->submitted_at ? date('d M Y H:i', strtotime($claim->submitted_at)) : '-'; ?></td>
                                            </tr>
                                            <tr>
                                                <td class="detail-label">Submission Attempts</td>
                                                <td><?php echo $claim->submission_attempts; ?></td>
                                            </tr>
                                            <?php if ($claim->rejection_reason): ?>
                                            <tr>
                                                <td class="detail-label">Rejection Reason</td>
                                                <td class="text-danger"><?php echo $claim->rejection_reason; ?></td>
                                            </tr>
                                            <?php endif; ?>
                                        </table>
                                    </div>
                                </div>

                                <?php if ($claim->diagnosis_codes): ?>
                                <hr>
                                <h4>Diagnosis Codes</h4>
                                <?php 
                                    $diagnoses = json_decode($claim->diagnosis_codes, true);
                                    if ($diagnoses):
                                ?>
                                <ul class="list-inline">
                                    <?php foreach ($diagnoses as $d): ?>
                                        <li>
                                            <span class="label label-default">
                                                <?php echo isset($d['code']) ? $d['code'] : ''; ?>
                                            </span>
                                            <?php echo isset($d['name']) ? $d['name'] : ''; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                                <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Claim Items -->
                        <div class="box box-info">
                            <div class="box-header with-border">
                                <h3 class="box-title"><i class="fa fa-list"></i> Claim Items</h3>
                            </div>
                            <div class="box-body table-responsive no-padding">
                                <table class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>Type</th>
                                            <th>Item</th>
                                            <th>NHIS Code</th>
                                            <th class="text-right">Qty</th>
                                            <th class="text-right">Unit Price</th>
                                            <th class="text-right">Total</th>
                                            <th class="text-right">NHIS Amt</th>
                                            <th class="text-right">Patient Amt</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($items)): ?>
                                            <?php 
                                                $totalAmount = 0;
                                                $totalNhis = 0;
                                                $totalPatient = 0;
                                                foreach ($items as $item): 
                                                    $totalAmount += $item->total_amount;
                                                    $totalNhis += $item->nhis_amount;
                                                    $totalPatient += $item->patient_amount;
                                            ?>
                                                <tr>
                                                    <td>
                                                        <?php
                                                            $typeClass = 'default';
                                                            switch ($item->item_type) {
                                                                case 'drug': $typeClass = 'success'; break;
                                                                case 'lab': $typeClass = 'info'; break;
                                                                case 'radiology': $typeClass = 'warning'; break;
                                                                case 'service': $typeClass = 'primary'; break;
                                                            }
                                                        ?>
                                                        <span class="label label-<?php echo $typeClass; ?> item-type">
                                                            <?php echo strtoupper($item->item_type); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo $item->item_name; ?></td>
                                                    <td><small><?php echo $item->nhis_code ?: '-'; ?></small></td>
                                                    <td class="text-right"><?php echo number_format($item->quantity, 2); ?></td>
                                                    <td class="text-right"><?php echo number_format($item->unit_price, 2); ?></td>
                                                    <td class="text-right"><?php echo number_format($item->total_amount, 2); ?></td>
                                                    <td class="text-right text-success"><?php echo number_format($item->nhis_amount, 2); ?></td>
                                                    <td class="text-right text-warning"><?php echo number_format($item->patient_amount, 2); ?></td>
                                                    <td>
                                                        <?php
                                                            $itemStatusClass = 'default';
                                                            switch ($item->item_status) {
                                                                case 'approved': $itemStatusClass = 'success'; break;
                                                                case 'rejected': $itemStatusClass = 'danger'; break;
                                                                case 'partial': $itemStatusClass = 'warning'; break;
                                                            }
                                                        ?>
                                                        <span class="label label-<?php echo $itemStatusClass; ?>">
                                                            <?php echo strtoupper($item->item_status); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr class="active">
                                            <th colspan="5" class="text-right">Totals:</th>
                                            <th class="text-right">GHS <?php echo number_format($totalAmount, 2); ?></th>
                                            <th class="text-right text-success">GHS <?php echo number_format($totalNhis, 2); ?></th>
                                            <th class="text-right text-warning">GHS <?php echo number_format($totalPatient, 2); ?></th>
                                            <th></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        <!-- Audit Log -->
                        <?php if (!empty($audit_log)): ?>
                        <div class="box box-default collapsed-box">
                            <div class="box-header with-border">
                                <h3 class="box-title"><i class="fa fa-history"></i> Audit Log</h3>
                                <div class="box-tools pull-right">
                                    <button type="button" class="btn btn-box-tool" data-widget="collapse">
                                        <i class="fa fa-plus"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="box-body table-responsive no-padding">
                                <table class="table table-condensed">
                                    <thead>
                                        <tr>
                                            <th>Date/Time</th>
                                            <th>Action</th>
                                            <th>Status</th>
                                            <th>User</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($audit_log as $log): ?>
                                            <tr>
                                                <td><small><?php echo date('d M Y H:i', strtotime($log->created_at)); ?></small></td>
                                                <td><?php echo ucwords(str_replace('_', ' ', $log->action_type)); ?></td>
                                                <td>
                                                    <span class="label label-<?php echo $log->status == 'success' ? 'success' : 'danger'; ?>">
                                                        <?php echo $log->status; ?>
                                                    </span>
                                                </td>
                                                <td><small><?php echo $log->performed_by; ?></small></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Actions & Summary -->
                    <div class="col-md-4">
                        <!-- Financial Summary -->
                        <div class="box box-success">
                            <div class="box-header with-border">
                                <h3 class="box-title"><i class="fa fa-money"></i> Financial Summary</h3>
                            </div>
                            <div class="box-body">
                                <table class="table table-condensed">
                                    <tr>
                                        <td>Total Claimed</td>
                                        <td class="text-right"><strong>GHS <?php echo number_format($claim->total_claim_amount, 2); ?></strong></td>
                                    </tr>
                                    <tr class="success">
                                        <td>Approved Amount</td>
                                        <td class="text-right">
                                            <strong>GHS <?php echo number_format($claim->approved_amount ?: 0, 2); ?></strong>
                                        </td>
                                    </tr>
                                    <tr class="warning">
                                        <td>Patient Co-pay</td>
                                        <td class="text-right">
                                            <strong>GHS <?php echo number_format($claim->patient_copay, 2); ?></strong>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="box box-primary">
                            <div class="box-header with-border">
                                <h3 class="box-title"><i class="fa fa-cogs"></i> Actions</h3>
                            </div>
                            <div class="box-body">
                                <?php if (in_array($claim->claim_status, array('draft', 'pending'))): ?>
                                    <a href="<?php echo base_url('app/nhis/submit_claim/' . $claim->id); ?>" 
                                       class="btn btn-block btn-primary"
                                       onclick="return confirm('Submit this claim to NHIS?')">
                                        <i class="fa fa-send"></i> Submit to NHIS
                                    </a>
                                <?php endif; ?>

                                <?php if ($claim->claim_status == 'submitted'): ?>
                                    <a href="<?php echo base_url('app/nhis/check_claim_status/' . $claim->id); ?>" 
                                       class="btn btn-block btn-info">
                                        <i class="fa fa-refresh"></i> Check Status
                                    </a>
                                <?php endif; ?>

                                <?php if ($claim->claim_status == 'rejected'): ?>
                                    <a href="<?php echo base_url('app/nhis/submit_claim/' . $claim->id); ?>" 
                                       class="btn btn-block btn-warning"
                                       onclick="return confirm('Resubmit this claim?')">
                                        <i class="fa fa-repeat"></i> Resubmit Claim
                                    </a>
                                <?php endif; ?>

                                <a href="<?php echo base_url('app/nhis/claims'); ?>" class="btn btn-block btn-default">
                                    <i class="fa fa-arrow-left"></i> Back to Claims
                                </a>
                            </div>
                        </div>

                        <!-- Timeline -->
                        <div class="box box-default">
                            <div class="box-header with-border">
                                <h3 class="box-title"><i class="fa fa-clock-o"></i> Timeline</h3>
                            </div>
                            <div class="box-body">
                                <ul class="timeline timeline-inverse">
                                    <li>
                                        <i class="fa fa-plus bg-blue"></i>
                                        <div class="timeline-item">
                                            <span class="time"><i class="fa fa-clock-o"></i> <?php echo date('d M Y', strtotime($claim->created_at)); ?></span>
                                            <h3 class="timeline-header">Claim Created</h3>
                                        </div>
                                    </li>
                                    <?php if ($claim->submitted_at): ?>
                                    <li>
                                        <i class="fa fa-send bg-aqua"></i>
                                        <div class="timeline-item">
                                            <span class="time"><i class="fa fa-clock-o"></i> <?php echo date('d M Y', strtotime($claim->submitted_at)); ?></span>
                                            <h3 class="timeline-header">Submitted to NHIS</h3>
                                        </div>
                                    </li>
                                    <?php endif; ?>
                                    <?php if ($claim->approved_at): ?>
                                    <li>
                                        <i class="fa fa-check bg-green"></i>
                                        <div class="timeline-item">
                                            <span class="time"><i class="fa fa-clock-o"></i> <?php echo date('d M Y', strtotime($claim->approved_at)); ?></span>
                                            <h3 class="timeline-header">Approved</h3>
                                        </div>
                                    </li>
                                    <?php endif; ?>
                                    <?php if ($claim->paid_at): ?>
                                    <li>
                                        <i class="fa fa-money bg-green"></i>
                                        <div class="timeline-item">
                                            <span class="time"><i class="fa fa-clock-o"></i> <?php echo date('d M Y', strtotime($claim->paid_at)); ?></span>
                                            <h3 class="timeline-header">Payment Received</h3>
                                        </div>
                                    </li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                    <div class="alert alert-warning">Claim not found.</div>
                <?php endif; ?>

            </section>
        </aside>
    </div>

    <script src="<?php echo base_url();?>public/js/jquery.min.js"></script>
    <script src="<?php echo base_url();?>public/js/bootstrap.min.js" type="text/javascript"></script>
    <script src="<?php echo base_url();?>public/js/AdminLTE/app.js" type="text/javascript"></script>
</body>
</html>
