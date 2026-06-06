<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Production Setup - HMS</title>
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
                <h1><i class="fa fa-cogs"></i> Production Setup & Hardening</h1>
                <ol class="breadcrumb">
                    <li><a href="<?php echo base_url(); ?>app/dashboard"><i class="fa fa-dashboard"></i> Dashboard</a></li>
                    <li class="active">Production Setup</li>
                </ol>
            </section>

            <section class="content">
            <?php 
            if (!isset($system_status)) $system_status = array('csrf_enabled' => false, 'session_db' => false, 'db_debug' => true);
            if (!isset($migration_status)) $migration_status = array();
            ?>
            <!-- System Status -->
            <div class="row">
                <div class="col-md-3">
                    <div class="small-box bg-<?php echo ENVIRONMENT === 'production' ? 'green' : 'yellow'; ?>">
                        <div class="inner">
                            <h3><?php echo strtoupper(ENVIRONMENT); ?></h3>
                            <p>Environment</p>
                        </div>
                        <div class="icon"><i class="fa fa-server"></i></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="small-box bg-<?php echo !empty($system_status['csrf_enabled']) ? 'green' : 'red'; ?>">
                        <div class="inner">
                            <h3><?php echo !empty($system_status['csrf_enabled']) ? 'ON' : 'OFF'; ?></h3>
                            <p>CSRF Protection</p>
                        </div>
                        <div class="icon"><i class="fa fa-shield"></i></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="small-box bg-<?php echo !empty($system_status['session_db']) ? 'green' : 'yellow'; ?>">
                        <div class="inner">
                            <h3><?php echo !empty($system_status['session_db']) ? 'DB' : 'FILE'; ?></h3>
                            <p>Session Storage</p>
                        </div>
                        <div class="icon"><i class="fa fa-database"></i></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="small-box bg-<?php echo empty($system_status['db_debug']) ? 'green' : 'yellow'; ?>">
                        <div class="inner">
                            <h3><?php echo !empty($system_status['db_debug']) ? 'ON' : 'OFF'; ?></h3>
                            <p>DB Debug</p>
                        </div>
                        <div class="icon"><i class="fa fa-bug"></i></div>
                    </div>
                </div>
            </div>

            <!-- Migration Status -->
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-database"></i> Migration Status</h3>
                    <div class="box-tools">
                        <button class="btn btn-success btn-sm" onclick="runMigrations()">
                            <i class="fa fa-play"></i> Run All Migrations
                        </button>
                    </div>
                </div>
                <div class="box-body">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Table</th>
                                <th>Status</th>
                                <th>Purpose</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $purposes = array(
                                'billing_transactions' => 'Single source of truth for all billing',
                                'billing_service_gates' => 'Prevent service before billing',
                                'billing_price_override_log' => 'Track price changes for audit',
                                'patient_consent' => 'Ghana Data Protection Act compliance',
                                'data_retention_policy' => 'Data retention per health regulations',
                                'reconciliation_issues' => 'Track billing discrepancies',
                                'system_audit_log' => 'Central audit trail',
                                'login_attempts' => 'Brute force protection',
                                'security_audit_log' => 'Security event logging',
                                'nhis_claims' => 'NHIS claim management'
                            );
                            foreach ($migration_status as $table => $installed): ?>
                            <tr>
                                <td><code><?php echo $table; ?></code></td>
                                <td>
                                    <?php if ($installed): ?>
                                        <span class="label label-success"><i class="fa fa-check"></i> Installed</span>
                                    <?php else: ?>
                                        <span class="label label-warning"><i class="fa fa-times"></i> Not Installed</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo isset($purposes[$table]) ? $purposes[$table] : ''; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Actions -->
            <div class="row">
                <div class="col-md-4">
                    <div class="box box-info">
                        <div class="box-header"><h3 class="box-title">Database Hardening</h3></div>
                        <div class="box-body">
                            <button class="btn btn-primary btn-block" onclick="convertInnoDB()">
                                <i class="fa fa-database"></i> Convert to InnoDB
                            </button>
                            <button class="btn btn-info btn-block" onclick="standardizeCharset()">
                                <i class="fa fa-font"></i> Standardize UTF8MB4
                            </button>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="box box-warning">
                        <div class="box-header"><h3 class="box-title">Reconciliation</h3></div>
                        <div class="box-body">
                            <button class="btn btn-warning btn-block" onclick="runReconciliation()">
                                <i class="fa fa-refresh"></i> Run Reconciliation
                            </button>
                            <a href="<?php echo base_url('app/production_setup/reconciliation_issues'); ?>" class="btn btn-default btn-block">
                                <i class="fa fa-list"></i> View Issues
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="box box-success">
                        <div class="box-header"><h3 class="box-title">Reports</h3></div>
                        <div class="box-body">
                            <button class="btn btn-success btn-block" onclick="getReadinessReport()">
                                <i class="fa fa-check-circle"></i> Readiness Report
                            </button>
                            <a href="<?php echo base_url('app/production_setup/audit_log'); ?>" class="btn btn-default btn-block">
                                <i class="fa fa-history"></i> Audit Log
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Readiness Report Display -->
            <div id="readiness-report" class="box box-solid" style="display:none;">
                <div class="box-header bg-blue"><h3 class="box-title">Production Readiness Report</h3></div>
                <div class="box-body" id="report-content"></div>
            </div>

        </section>
    </aside>
</div>

<script src="<?php echo base_url(); ?>public/js/jquery.min.js"></script>
<script src="<?php echo base_url(); ?>public/js/bootstrap.min.js"></script>
<script src="<?php echo base_url(); ?>public/js/AdminLTE/app.js"></script>
<script>
var csrfName = '<?php echo $this->security->get_csrf_token_name(); ?>';
var csrfHash = '<?php echo $this->security->get_csrf_hash(); ?>';

function runMigrations() {
    if (!confirm('Run all production migrations?')) return;
    var postData = {};
    postData[csrfName] = csrfHash;
    $.post('<?php echo base_url("app/production_setup/run_migrations"); ?>', postData, function(data) {
        var res = JSON.parse(data);
        if (res.ok) {
            alert('Migrations completed successfully!');
            location.reload();
        } else {
            alert('Error: ' + (res.error || 'Unknown error'));
        }
    }).fail(function(xhr) {
        alert('Request failed: ' + xhr.status + ' - ' + xhr.statusText);
    });
}

function convertInnoDB() {
    if (!confirm('Convert all tables to InnoDB? This may take a while.')) return;
    var postData = {};
    postData[csrfName] = csrfHash;
    $.post('<?php echo base_url("app/production_setup/convert_innodb"); ?>', postData, function(data) {
        var res = JSON.parse(data);
        alert('InnoDB conversion complete. Tables processed: ' + Object.keys(res.results).length);
    }).fail(function(xhr) {
        alert('Request failed: ' + xhr.status);
    });
}

function standardizeCharset() {
    if (!confirm('Standardize all tables to UTF8MB4?')) return;
    var postData = {};
    postData[csrfName] = csrfHash;
    $.post('<?php echo base_url("app/production_setup/standardize_charset"); ?>', postData, function(data) {
        var res = JSON.parse(data);
        alert('Charset standardization complete. Tables: ' + res.tables_converted);
    }).fail(function(xhr) {
        alert('Request failed: ' + xhr.status);
    });
}

function runReconciliation() {
    var postData = {};
    postData[csrfName] = csrfHash;
    $.post('<?php echo base_url("app/production_setup/run_reconciliation"); ?>', postData, function(data) {
        var res = JSON.parse(data);
        alert('Reconciliation complete. Issues found: ' + res.results.issues_found);
    }).fail(function(xhr) {
        alert('Request failed: ' + xhr.status);
    });
}

function getReadinessReport() {
    $.get('<?php echo base_url("app/production_setup/readiness_report"); ?>', function(data) {
        var r = JSON.parse(data);
        var html = '<div class="row">';
        html += '<div class="col-md-4"><div class="info-box bg-' + (r.percentage >= 90 ? 'green' : (r.percentage >= 70 ? 'yellow' : 'red')) + '">';
        html += '<span class="info-box-icon"><i class="fa fa-tachometer"></i></span>';
        html += '<div class="info-box-content"><span class="info-box-text">Score</span>';
        html += '<span class="info-box-number">' + r.score + '/' + r.max_score + ' (' + r.percentage + '%)</span>';
        html += '<span class="progress-description">' + r.status + '</span></div></div></div>';
        
        html += '<div class="col-md-8"><h4>Breakdown</h4><ul>';
        for (var k in r.breakdown) {
            html += '<li><strong>' + k + ':</strong> ' + r.breakdown[k] + '</li>';
        }
        html += '</ul>';
        
        if (r.findings.length > 0) {
            html += '<h4>Findings</h4><ul>';
            r.findings.forEach(function(f) {
                var cls = f.severity === 'CRITICAL' ? 'danger' : (f.severity === 'HIGH' ? 'warning' : 'info');
                html += '<li><span class="label label-' + cls + '">' + f.severity + '</span> ' + f.item + '</li>';
            });
            html += '</ul>';
        }
        html += '</div></div>';
        
        $('#report-content').html(html);
        $('#readiness-report').show();
    });
}
</script>
</body>
</html>
