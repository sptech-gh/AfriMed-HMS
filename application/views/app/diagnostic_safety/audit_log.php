<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo $title; ?> - HMS</title>
    <meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>
    <link href="<?php echo base_url(); ?>public/bootstrap/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/font-awesome/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/dist/css/AdminLTE.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/dist/css/skins/skin-blue.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/plugins/datatables/dataTables.bootstrap.css" rel="stylesheet" type="text/css" />
</head>
<body class="skin-blue sidebar-mini">
<?php require_once(APPPATH.'views/include/header.php');?>
<div class="wrapper row-offcanvas row-offcanvas-left">
<?php require_once(APPPATH.'views/include/sidebar.php');?>
<aside class="right-side">
<section class="content-header">
    <h1><i class="fa fa-history"></i> Audit Trail</h1>
    <ol class="breadcrumb">
        <li><a href="<?php echo base_url();?>app/dashboard"><i class="fa fa-dashboard"></i> Dashboard</a></li>
        <li><a href="<?php echo base_url();?>app/diagnostic_safety">Diagnostic Safety</a></li>
        <li class="active">Audit Log</li>
    </ol>
</section>

<section class="content">
    <!-- Stats Row -->
    <div class="row">
        <div class="col-md-2 col-sm-4 col-xs-6">
            <div class="info-box bg-aqua">
                <span class="info-box-icon"><i class="fa fa-list"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Total Events</span>
                    <span class="info-box-number"><?php echo number_format($stats->total_events ?? 0); ?></span>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-4 col-xs-6">
            <div class="info-box bg-green">
                <span class="info-box-icon"><i class="fa fa-flask"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Result Events</span>
                    <span class="info-box-number"><?php echo number_format($stats->result_events ?? 0); ?></span>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-4 col-xs-6">
            <div class="info-box bg-yellow">
                <span class="info-box-icon"><i class="fa fa-warning"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Warnings</span>
                    <span class="info-box-number"><?php echo number_format($stats->warning_events ?? 0); ?></span>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-4 col-xs-6">
            <div class="info-box bg-red">
                <span class="info-box-icon"><i class="fa fa-exclamation-circle"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Critical</span>
                    <span class="info-box-number"><?php echo number_format($stats->critical_events ?? 0); ?></span>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-4 col-xs-6">
            <div class="info-box bg-purple">
                <span class="info-box-icon"><i class="fa fa-lock"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Security</span>
                    <span class="info-box-number"><?php echo number_format($stats->security_events ?? 0); ?></span>
                </div>
        </div>
        </div>
        <div class="col-md-2 col-sm-4 col-xs-6">
            <div class="info-box bg-maroon">
                <span class="info-box-icon"><i class="fa fa-users"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Unique Users</span>
                    <span class="info-box-number"><?php echo number_format($stats->unique_users ?? 0); ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="box box-default collapsed-box">
        <div class="box-header with-border">
            <h3 class="box-title"><i class="fa fa-filter"></i> Filters</h3>
            <div class="box-tools pull-right">
                <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-plus"></i></button>
            </div>
        </div>
        <div class="box-body">
            <form method="get" action="">
                <div class="row">
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Category</label>
                            <select name="category" class="form-control">
                                <option value="">All Categories</option>
                                <option value="RESULT" <?php echo ($filters['event_category'] ?? '') == 'RESULT' ? 'selected' : ''; ?>>Result</option>
                                <option value="SAMPLE" <?php echo ($filters['event_category'] ?? '') == 'SAMPLE' ? 'selected' : ''; ?>>Sample</option>
                                <option value="ORDER" <?php echo ($filters['event_category'] ?? '') == 'ORDER' ? 'selected' : ''; ?>>Order</option>
                                <option value="ACCESS" <?php echo ($filters['event_category'] ?? '') == 'ACCESS' ? 'selected' : ''; ?>>Access</option>
                                <option value="CONFIG" <?php echo ($filters['event_category'] ?? '') == 'CONFIG' ? 'selected' : ''; ?>>Config</option>
                                <option value="SECURITY" <?php echo ($filters['event_category'] ?? '') == 'SECURITY' ? 'selected' : ''; ?>>Security</option>
                                <option value="SYSTEM" <?php echo ($filters['event_category'] ?? '') == 'SYSTEM' ? 'selected' : ''; ?>>System</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Severity</label>
                            <select name="severity" class="form-control">
                                <option value="">All Severities</option>
                                <option value="INFO" <?php echo ($filters['severity'] ?? '') == 'INFO' ? 'selected' : ''; ?>>Info</option>
                                <option value="WARNING" <?php echo ($filters['severity'] ?? '') == 'WARNING' ? 'selected' : ''; ?>>Warning</option>
                                <option value="ERROR" <?php echo ($filters['severity'] ?? '') == 'ERROR' ? 'selected' : ''; ?>>Error</option>
                                <option value="CRITICAL" <?php echo ($filters['severity'] ?? '') == 'CRITICAL' ? 'selected' : ''; ?>>Critical</option>
                                <option value="SECURITY" <?php echo ($filters['severity'] ?? '') == 'SECURITY' ? 'selected' : ''; ?>>Security</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Patient No</label>
                            <input type="text" name="patient_no" class="form-control" value="<?php echo htmlspecialchars($filters['patient_no'] ?? ''); ?>" placeholder="Patient No">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Date From</label>
                            <input type="date" name="date_from" class="form-control" value="<?php echo $filters['date_from'] ?? ''; ?>">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Date To</label>
                            <input type="date" name="date_to" class="form-control" value="<?php echo $filters['date_to'] ?? ''; ?>">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Search</label>
                            <input type="text" name="search" class="form-control" value="<?php echo htmlspecialchars($filters['search'] ?? ''); ?>" placeholder="Search...">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> Filter</button>
                        <a href="<?php echo base_url('app/diagnostic_safety/audit_log'); ?>" class="btn btn-default">Reset</a>
                        <a href="<?php echo base_url('app/diagnostic_safety/export_audit?' . http_build_query($filters)); ?>" class="btn btn-success pull-right"><i class="fa fa-download"></i> Export CSV</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Audit Log Table -->
    <div class="box box-primary">
        <div class="box-header with-border">
            <h3 class="box-title">Audit Events</h3>
            <div class="box-tools pull-right">
                <span class="badge bg-blue"><?php echo number_format($total); ?> records</span>
            </div>
        </div>
        <div class="box-body table-responsive">
            <table class="table table-bordered table-striped table-hover" id="auditTable">
                <thead>
                    <tr>
                        <th width="140">Timestamp</th>
                        <th>Event</th>
                        <th>Category</th>
                        <th>Severity</th>
                        <th>Entity</th>
                        <th>Patient</th>
                        <th>User</th>
                        <th>Summary</th>
                        <th width="60">Hash</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><small><?php echo date('Y-m-d H:i:s', strtotime($log->created_at)); ?></small></td>
                        <td><code><?php echo htmlspecialchars($log->event_type); ?></code></td>
                        <td>
                            <?php
                            $cat_colors = [
                                'RESULT' => 'success',
                                'SAMPLE' => 'info',
                                'ORDER' => 'primary',
                                'ACCESS' => 'default',
                                'CONFIG' => 'warning',
                                'SECURITY' => 'danger',
                                'SYSTEM' => 'default'
                            ];
                            $cat_color = $cat_colors[$log->event_category] ?? 'default';
                            ?>
                            <span class="label label-<?php echo $cat_color; ?>"><?php echo $log->event_category; ?></span>
                        </td>
                        <td>
                            <?php
                            $sev_colors = [
                                'INFO' => 'info',
                                'WARNING' => 'warning',
                                'ERROR' => 'danger',
                                'CRITICAL' => 'danger',
                                'SECURITY' => 'danger'
                            ];
                            $sev_color = $sev_colors[$log->severity] ?? 'default';
                            ?>
                            <span class="label label-<?php echo $sev_color; ?>"><?php echo $log->severity; ?></span>
                        </td>
                        <td>
                            <?php if ($log->entity_id): ?>
                            <a href="<?php echo base_url("app/diagnostic_safety/entity_audit/{$log->entity_type}/{$log->entity_id}"); ?>">
                                <?php echo htmlspecialchars($log->entity_type); ?> #<?php echo htmlspecialchars($log->entity_id); ?>
                            </a>
                            <?php else: ?>
                            <?php echo htmlspecialchars($log->entity_type); ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($log->patient_no): ?>
                            <a href="<?php echo base_url("app/diagnostic_safety/patient_audit/{$log->patient_no}"); ?>">
                                <?php echo htmlspecialchars($log->patient_no); ?>
                            </a>
                            <?php else: ?>
                            -
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($log->performer_name ?? $log->username ?? '-'); ?></td>
                        <td><small><?php echo htmlspecialchars(substr($log->change_summary ?? $log->action, 0, 50)); ?></small></td>
                        <td>
                            <?php if (!$log->is_verified): ?>
                            <span class="text-red" title="Verification failed"><i class="fa fa-warning"></i></span>
                            <?php else: ?>
                            <span class="text-green" title="<?php echo $log->record_hash; ?>"><i class="fa fa-check"></i></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="box-footer clearfix">
            <?php if ($total_pages > 1): ?>
            <ul class="pagination pagination-sm no-margin pull-right">
                <?php if ($page > 1): ?>
                <li><a href="?<?php echo http_build_query(array_merge($filters, ['page' => $page - 1])); ?>">&laquo;</a></li>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                <li class="<?php echo $i == $page ? 'active' : ''; ?>">
                    <a href="?<?php echo http_build_query(array_merge($filters, ['page' => $i])); ?>"><?php echo $i; ?></a>
                </li>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                <li><a href="?<?php echo http_build_query(array_merge($filters, ['page' => $page + 1])); ?>">&raquo;</a></li>
                <?php endif; ?>
            </ul>
            <?php endif; ?>
        </div>
    </div>

</section>
</aside>
</div>

<script src="<?php echo base_url(); ?>public/plugins/jQuery/jQuery-2.1.4.min.js"></script>
<script src="<?php echo base_url(); ?>public/bootstrap/js/bootstrap.min.js"></script>
<script src="<?php echo base_url(); ?>public/dist/js/app.min.js"></script>
</body>
</html>
