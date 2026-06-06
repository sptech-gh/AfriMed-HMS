<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>NHIS Dashboard - Hebrew Medical Center</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">

    <link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />

    <style>
        .small-box .icon { font-size: 70px; }
        .claim-status { font-size: 11px; padding: 4px 8px; }
        .mode-badge { font-size: 14px; padding: 8px 16px; }
        .table td { vertical-align: middle !important; }
    </style>
</head>
<body class="skin-blue">
    <?php require_once(APPPATH.'views/include/header.php');?>

    <div class="wrapper row-offcanvas row-offcanvas-left">
        <?php require_once(APPPATH.'views/include/sidebar.php');?>

        <aside class="right-side">
            <section class="content-header">
                <h1>
                    NHIS Dashboard
                    <?php if (isset($mode)): ?>
                        <span class="label <?php echo $mode === 'mock' ? 'label-warning' : 'label-success'; ?> mode-badge">
                            <i class="fa fa-<?php echo $mode === 'mock' ? 'flask' : 'check-circle'; ?>"></i>
                            <?php echo strtoupper($mode); ?> MODE
                        </span>
                    <?php endif; ?>
                </h1>
                <ol class="breadcrumb">
                    <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                    <li class="active">NHIS</li>
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

                <?php
                    $stats = isset($stats) ? $stats : array();
                    $todayClaims = isset($stats['today_claims']) ? $stats['today_claims'] : 0;
                    $pendingClaims = isset($stats['pending_claims']) ? $stats['pending_claims'] : 0;
                    $submittedClaims = isset($stats['submitted_claims']) ? $stats['submitted_claims'] : 0;
                    $approvedCount = isset($stats['approved_count']) ? $stats['approved_count'] : 0;
                    $approvedAmount = isset($stats['approved_amount']) ? $stats['approved_amount'] : 0;
                    $rejectedClaims = isset($stats['rejected_claims']) ? $stats['rejected_claims'] : 0;
                    $monthClaimed = isset($stats['month_claimed']) ? $stats['month_claimed'] : 0;
                    $openIssues = isset($stats['open_issues']) ? $stats['open_issues'] : 0;
                ?>

                <!-- Summary Boxes -->
                <div class="row">
                    <div class="col-md-3">
                        <div class="small-box bg-aqua">
                            <div class="inner">
                                <h3><?php echo $todayClaims; ?></h3>
                                <p>Today's Claims</p>
                            </div>
                            <div class="icon"><i class="fa fa-file-text"></i></div>
                            <a href="<?php echo base_url('app/nhis/claims'); ?>" class="small-box-footer">
                                View All <i class="fa fa-arrow-circle-right"></i>
                            </a>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="small-box bg-yellow">
                            <div class="inner">
                                <h3><?php echo $pendingClaims; ?></h3>
                                <p>Pending Claims</p>
                            </div>
                            <div class="icon"><i class="fa fa-clock-o"></i></div>
                            <a href="<?php echo base_url('app/nhis/claims?status=pending'); ?>" class="small-box-footer">
                                View Pending <i class="fa fa-arrow-circle-right"></i>
                            </a>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="small-box bg-green">
                            <div class="inner">
                                <h3><?php echo $approvedCount; ?></h3>
                                <p>Approved (This Month)</p>
                            </div>
                            <div class="icon"><i class="fa fa-check-circle"></i></div>
                            <a href="<?php echo base_url('app/nhis/claims?status=approved'); ?>" class="small-box-footer">
                                View Approved <i class="fa fa-arrow-circle-right"></i>
                            </a>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="small-box bg-red">
                            <div class="inner">
                                <h3><?php echo $openIssues; ?></h3>
                                <p>Reconciliation Issues</p>
                            </div>
                            <div class="icon"><i class="fa fa-exclamation-triangle"></i></div>
                            <a href="<?php echo base_url('app/nhis/reconciliation'); ?>" class="small-box-footer">
                                View Issues <i class="fa fa-arrow-circle-right"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Financial Summary -->
                <div class="row">
                    <div class="col-md-4">
                        <div class="info-box bg-blue">
                            <span class="info-box-icon"><i class="fa fa-money"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Month Claimed</span>
                                <span class="info-box-number">GHS <?php echo number_format($monthClaimed, 2); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="info-box bg-green">
                            <span class="info-box-icon"><i class="fa fa-thumbs-up"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Month Approved</span>
                                <span class="info-box-number">GHS <?php echo number_format($approvedAmount, 2); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="info-box bg-orange">
                            <span class="info-box-icon"><i class="fa fa-send"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Awaiting Response</span>
                                <span class="info-box-number"><?php echo $submittedClaims; ?> Claims</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Recent Claims -->
                    <div class="col-md-8">
                        <div class="box box-primary">
                            <div class="box-header with-border">
                                <h3 class="box-title"><i class="fa fa-list"></i> Recent Claims</h3>
                                <div class="box-tools pull-right">
                                    <a href="<?php echo base_url('app/nhis/claims'); ?>" class="btn btn-sm btn-primary">
                                        View All
                                    </a>
                                </div>
                            </div>
                            <div class="box-body table-responsive no-padding">
                                <table class="table table-hover table-striped">
                                    <thead>
                                        <tr>
                                            <th>Claim #</th>
                                            <th>Patient</th>
                                            <th>Visit Date</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($recent_claims)): ?>
                                            <?php foreach ($recent_claims as $claim): ?>
                                                <tr>
                                                    <td><strong><?php echo $claim->claim_number; ?></strong></td>
                                                    <td>
                                                        <?php echo $claim->firstname . ' ' . $claim->lastname; ?>
                                                        <br><small class="text-muted"><?php echo $claim->patient_no; ?></small>
                                                    </td>
                                                    <td><?php echo date('d M Y', strtotime($claim->visit_date)); ?></td>
                                                    <td>GHS <?php echo number_format($claim->total_claim_amount, 2); ?></td>
                                                    <td>
                                                        <?php
                                                            $statusClass = 'default';
                                                            switch ($claim->claim_status) {
                                                                case 'approved': $statusClass = 'success'; break;
                                                                case 'rejected': $statusClass = 'danger'; break;
                                                                case 'submitted': $statusClass = 'info'; break;
                                                                case 'pending': $statusClass = 'warning'; break;
                                                                case 'paid': $statusClass = 'primary'; break;
                                                            }
                                                        ?>
                                                        <span class="label label-<?php echo $statusClass; ?> claim-status">
                                                            <?php echo strtoupper($claim->claim_status); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <a href="<?php echo base_url('app/nhis/claim/' . $claim->id); ?>" 
                                                           class="btn btn-xs btn-default">
                                                            <i class="fa fa-eye"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="6" class="text-center text-muted">No claims found</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions & Pending Claims -->
                    <div class="col-md-4">
                        <!-- Quick Actions -->
                        <div class="box box-success">
                            <div class="box-header with-border">
                                <h3 class="box-title"><i class="fa fa-bolt"></i> Quick Actions</h3>
                            </div>
                            <div class="box-body">
                                <a href="<?php echo base_url('app/nhis/claims'); ?>" class="btn btn-block btn-primary">
                                    <i class="fa fa-list"></i> View All Claims
                                </a>
                                <a href="<?php echo base_url('app/nhis/coverage'); ?>" class="btn btn-block btn-info">
                                    <i class="fa fa-shield"></i> Manage Coverage
                                </a>
                                <a href="<?php echo base_url('app/nhis/reconciliation'); ?>" class="btn btn-block btn-warning">
                                    <i class="fa fa-balance-scale"></i> Reconciliation
                                </a>
                                <a href="<?php echo base_url('app/nhis/reports'); ?>" class="btn btn-block btn-success">
                                    <i class="fa fa-bar-chart"></i> Reports
                                </a>
                                <a href="<?php echo base_url('app/nhis/audit_log'); ?>" class="btn btn-block btn-default">
                                    <i class="fa fa-history"></i> Audit Log
                                </a>
                            </div>
                        </div>

                        <!-- Pending Claims -->
                        <div class="box box-warning">
                            <div class="box-header with-border">
                                <h3 class="box-title"><i class="fa fa-clock-o"></i> Pending Submission</h3>
                            </div>
                            <div class="box-body no-padding">
                                <ul class="nav nav-stacked">
                                    <?php if (!empty($pending_claims)): ?>
                                        <?php foreach ($pending_claims as $claim): ?>
                                            <li>
                                                <a href="<?php echo base_url('app/nhis/claim/' . $claim->id); ?>">
                                                    <?php echo $claim->claim_number; ?>
                                                    <span class="pull-right text-muted">
                                                        GHS <?php echo number_format($claim->total_claim_amount, 2); ?>
                                                    </span>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <li><a href="#" class="text-muted">No pending claims</a></li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>

                        <!-- Reconciliation Issues -->
                        <?php if (!empty($reconciliation_issues)): ?>
                        <div class="box box-danger">
                            <div class="box-header with-border">
                                <h3 class="box-title"><i class="fa fa-exclamation-triangle"></i> Open Issues</h3>
                            </div>
                            <div class="box-body no-padding">
                                <ul class="nav nav-stacked">
                                    <?php foreach ($reconciliation_issues as $issue): ?>
                                        <li>
                                            <a href="<?php echo base_url('app/nhis/reconciliation'); ?>">
                                                <?php echo ucwords(str_replace('_', ' ', $issue->issue_type)); ?>
                                                <span class="pull-right badge bg-red">
                                                    <?php echo $issue->reference_id; ?>
                                                </span>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                        <?php endif; ?>
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
