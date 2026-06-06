<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo $title; ?> | HMS</title>
    <meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>
    <link href="<?php echo base_url() ?>public/bootstrap/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url() ?>public/font-awesome/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url() ?>public/dist/css/AdminLTE.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url() ?>public/dist/css/skins/skin-blue.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url() ?>public/plugins/datepicker/datepicker3.css" rel="stylesheet" type="text/css" />
    <style>
        .stat-card { background: #fff; padding: 20px; border-radius: 4px; margin-bottom: 15px; border-left: 4px solid; }
        .stat-card.primary { border-color: #3c8dbc; }
        .stat-card.success { border-color: #00a65a; }
        .stat-card.warning { border-color: #f39c12; }
        .stat-card.danger { border-color: #dd4b39; }
        .stat-card h2 { margin: 0 0 5px 0; }
        .stat-card p { margin: 0; color: #666; }
    </style>
</head>
<body class="skin-blue sidebar-mini">
<?php require_once(APPPATH . 'views/include/header.php'); ?>

<div class="wrapper row-offcanvas row-offcanvas-left">
    <?php require_once(APPPATH . 'views/include/sidebar.php'); ?>
    
    <aside class="right-side">
        <section class="content-header">
            <h1><i class="fa fa-bar-chart"></i> <?php echo $title; ?></h1>
            <ol class="breadcrumb">
                <li><a href="<?php echo base_url() ?>app/dashboard"><i class="fa fa-dashboard"></i> Dashboard</a></li>
                <li><a href="<?php echo base_url() ?>app/result_approval">Result Approval</a></li>
                <li class="active">Reports</li>
            </ol>
        </section>

        <section class="content">
            <!-- Date Filter -->
            <div class="box box-default">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-filter"></i> Report Period</h3>
                </div>
                <div class="box-body">
                    <form method="get" class="form-inline">
                        <div class="form-group">
                            <label>From:</label>
                            <input type="text" name="date_from" class="form-control datepicker" value="<?php echo $date_from; ?>">
                        </div>
                        <div class="form-group" style="margin-left: 15px;">
                            <label>To:</label>
                            <input type="text" name="date_to" class="form-control datepicker" value="<?php echo $date_to; ?>">
                        </div>
                        <button type="submit" class="btn btn-primary" style="margin-left: 15px;">
                            <i class="fa fa-search"></i> Generate Report
                        </button>
                        <button type="button" class="btn btn-default" onclick="window.print();" style="margin-left: 10px;">
                            <i class="fa fa-print"></i> Print
                        </button>
                    </form>
                </div>
            </div>

            <!-- Approval Statistics -->
            <div class="row">
                <div class="col-md-3">
                    <div class="stat-card primary">
                        <h2><?php echo $approval_stats->total_requests ?? 0; ?></h2>
                        <p><i class="fa fa-file-text"></i> Total Requests</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card success">
                        <h2><?php echo $approval_stats->approved ?? 0; ?></h2>
                        <p><i class="fa fa-check"></i> Approved</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card danger">
                        <h2><?php echo $approval_stats->rejected ?? 0; ?></h2>
                        <p><i class="fa fa-times"></i> Rejected</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card warning">
                        <h2><?php echo $approval_stats->pending ?? 0; ?></h2>
                        <p><i class="fa fa-clock-o"></i> Pending</p>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Approval Summary -->
                <div class="col-md-6">
                    <div class="box box-primary">
                        <div class="box-header with-border">
                            <h3 class="box-title"><i class="fa fa-pie-chart"></i> Approval Summary</h3>
                        </div>
                        <div class="box-body">
                            <table class="table table-bordered">
                                <tr>
                                    <td>Total Requests</td>
                                    <td class="text-right"><strong><?php echo $approval_stats->total_requests ?? 0; ?></strong></td>
                                </tr>
                                <tr class="success">
                                    <td>Approved</td>
                                    <td class="text-right"><?php echo $approval_stats->approved ?? 0; ?></td>
                                </tr>
                                <tr class="danger">
                                    <td>Rejected</td>
                                    <td class="text-right"><?php echo $approval_stats->rejected ?? 0; ?></td>
                                </tr>
                                <tr class="warning">
                                    <td>Pending</td>
                                    <td class="text-right"><?php echo $approval_stats->pending ?? 0; ?></td>
                                </tr>
                                <tr class="info">
                                    <td>Escalated</td>
                                    <td class="text-right"><?php echo $approval_stats->escalated ?? 0; ?></td>
                                </tr>
                                <tr>
                                    <td>Expired</td>
                                    <td class="text-right"><?php echo $approval_stats->expired ?? 0; ?></td>
                                </tr>
                                <tr>
                                    <td>STAT Requests</td>
                                    <td class="text-right"><span class="label label-danger"><?php echo $approval_stats->stat_requests ?? 0; ?></span></td>
                                </tr>
                                <tr>
                                    <td>Urgent Requests</td>
                                    <td class="text-right"><span class="label label-warning"><?php echo $approval_stats->urgent_requests ?? 0; ?></span></td>
                                </tr>
                                <tr>
                                    <td>Avg Response Time</td>
                                    <td class="text-right"><strong><?php echo round($approval_stats->avg_response_minutes ?? 0); ?> min</strong></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Verification Summary -->
                <div class="col-md-6">
                    <div class="box box-info">
                        <div class="box-header with-border">
                            <h3 class="box-title"><i class="fa fa-shield"></i> Verification Summary</h3>
                        </div>
                        <div class="box-body">
                            <table class="table table-bordered">
                                <tr>
                                    <td>Total Verification Attempts</td>
                                    <td class="text-right"><strong><?php echo $verification_stats->total_attempts ?? 0; ?></strong></td>
                                </tr>
                                <tr class="success">
                                    <td>Successful Verifications</td>
                                    <td class="text-right"><?php echo $verification_stats->successful ?? 0; ?></td>
                                </tr>
                                <tr class="danger">
                                    <td>Total Denied</td>
                                    <td class="text-right"><?php echo $verification_stats->denied ?? 0; ?></td>
                                </tr>
                                <tr>
                                    <td colspan="2"><strong>Denial Breakdown:</strong></td>
                                </tr>
                                <tr>
                                    <td class="text-muted" style="padding-left: 30px;">- Role Not Authorized</td>
                                    <td class="text-right"><?php echo $verification_stats->denied_role ?? 0; ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted" style="padding-left: 30px;">- Credential Issue</td>
                                    <td class="text-right"><?php echo $verification_stats->denied_credential ?? 0; ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted" style="padding-left: 30px;">- Same User Violation</td>
                                    <td class="text-right"><?php echo $verification_stats->denied_same_user ?? 0; ?></td>
                                </tr>
                                <?php 
                                $total = $verification_stats->total_attempts ?? 1;
                                $success = $verification_stats->successful ?? 0;
                                $rate = $total > 0 ? round(($success / $total) * 100, 1) : 0;
                                ?>
                                <tr class="active">
                                    <td><strong>Success Rate</strong></td>
                                    <td class="text-right">
                                        <strong><?php echo $rate; ?>%</strong>
                                        <div class="progress progress-xs" style="margin: 5px 0 0 0;">
                                            <div class="progress-bar progress-bar-success" style="width: <?php echo $rate; ?>%"></div>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Key Metrics -->
            <div class="box box-success">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-line-chart"></i> Key Performance Indicators</h3>
                </div>
                <div class="box-body">
                    <div class="row">
                        <div class="col-md-4 text-center">
                            <?php 
                            $approval_rate = ($approval_stats->total_requests ?? 0) > 0 
                                ? round((($approval_stats->approved ?? 0) / $approval_stats->total_requests) * 100, 1) 
                                : 0;
                            ?>
                            <h1 class="text-green"><?php echo $approval_rate; ?>%</h1>
                            <p>Approval Rate</p>
                        </div>
                        <div class="col-md-4 text-center">
                            <h1 class="text-blue"><?php echo round($approval_stats->avg_response_minutes ?? 0); ?></h1>
                            <p>Avg Response Time (min)</p>
                        </div>
                        <div class="col-md-4 text-center">
                            <h1 class="text-yellow"><?php echo $approval_stats->escalated ?? 0; ?></h1>
                            <p>Escalations</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Compliance Notes -->
            <div class="callout callout-info">
                <h4><i class="fa fa-info-circle"></i> Report Notes</h4>
                <ul>
                    <li>This report covers the period from <strong><?php echo $date_from; ?></strong> to <strong><?php echo $date_to; ?></strong>.</li>
                    <li>Approval rate is calculated as (Approved / Total Requests) × 100.</li>
                    <li>Average response time measures the time from request creation to review completion.</li>
                    <li>Verification denials are logged for audit and compliance purposes.</li>
                </ul>
            </div>
        </section>
    </aside>
</div>

<script src="<?php echo base_url() ?>public/plugins/jQuery/jQuery-2.1.4.min.js"></script>
<script src="<?php echo base_url() ?>public/bootstrap/js/bootstrap.min.js"></script>
<script src="<?php echo base_url() ?>public/plugins/datepicker/bootstrap-datepicker.js"></script>
<script src="<?php echo base_url() ?>public/dist/js/app.min.js"></script>
<script>
$(function() {
    $('.datepicker').datepicker({ format: 'yyyy-mm-dd', autoclose: true });
});
</script>
</body>
</html>
