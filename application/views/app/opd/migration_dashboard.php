<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>OPD Status Migration - <?php echo $this->config->item('system_name'); ?></title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <link href="<?php echo base_url(); ?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
    <style>
        .stat-box { text-align: center; padding: 20px; border-radius: 5px; margin-bottom: 15px; }
        .stat-box h2 { margin: 0; font-size: 48px; }
        .stat-box.success { background: #d4edda; border: 2px solid #28a745; }
        .stat-box.warning { background: #fff3cd; border: 2px solid #ffc107; }
        .stat-box.danger { background: #f8d7da; border: 2px solid #dc3545; }
        .stat-box.info { background: #d1ecf1; border: 2px solid #17a2b8; }
        .migration-log { max-height: 400px; overflow-y: auto; background: #1a1a2e; color: #0f0; padding: 15px; font-family: monospace; font-size: 12px; border-radius: 4px; }
        .preview-table { max-height: 400px; overflow-y: auto; }
    </style>
</head>
<body class="skin-blue">
    <?php require_once(APPPATH . 'views/include/header.php'); ?>
    
    <div class="wrapper row-offcanvas row-offcanvas-left">
        <?php require_once(APPPATH . 'views/include/sidebar.php'); ?>
        
        <aside class="right-side">
            <section class="content-header">
                <h1>
                    <i class="fa fa-database"></i> OPD Status Migration
                    <small>Unify status tracking</small>
                </h1>
                <ol class="breadcrumb">
                    <li><a href="<?php echo base_url(); ?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                    <li><a href="<?php echo base_url(); ?>app/opd">OPD</a></li>
                    <li class="active">Migration</li>
                </ol>
            </section>

            <section class="content">
                <?php if (isset($message) && $message): ?>
                    <?php echo $message; ?>
                <?php endif; ?>

                <!-- Stats Overview -->
                <div class="row">
                    <div class="col-md-3">
                        <div class="stat-box info">
                            <h2><?php echo number_format($stats['total_visits']); ?></h2>
                            <p><i class="fa fa-users"></i> Total Visits</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-box success">
                            <h2><?php echo number_format($stats['with_workflow']); ?></h2>
                            <p><i class="fa fa-check"></i> With Workflow</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-box warning">
                            <h2><?php echo number_format($stats['without_workflow']); ?></h2>
                            <p><i class="fa fa-exclamation-triangle"></i> Missing Workflow</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-box danger">
                            <h2><?php echo number_format($stats['inconsistencies']); ?></h2>
                            <p><i class="fa fa-times-circle"></i> Inconsistencies</p>
                        </div>
                    </div>
                </div>

                <!-- Status Distribution -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="box box-info">
                            <div class="box-header with-border">
                                <h3 class="box-title"><i class="fa fa-history"></i> Legacy Status (nStatus)</h3>
                            </div>
                            <div class="box-body">
                                <table class="table table-condensed">
                                    <thead><tr><th>Status</th><th>Count</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($stats['legacy_status'] as $s): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($s->nStatus ?: '(empty)'); ?></td>
                                            <td><span class="badge"><?php echo number_format($s->cnt); ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="box box-success">
                            <div class="box-header with-border">
                                <h3 class="box-title"><i class="fa fa-cogs"></i> Workflow Status (Unified)</h3>
                            </div>
                            <div class="box-body">
                                <table class="table table-condensed">
                                    <thead><tr><th>Status</th><th>Count</th></tr></thead>
                                    <tbody>
                                    <?php if (empty($stats['workflow_status'])): ?>
                                        <tr><td colspan="2" class="text-muted">No workflow records yet</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($stats['workflow_status'] as $s): ?>
                                        <tr>
                                            <td><span class="label label-default"><?php echo htmlspecialchars($s->status); ?></span></td>
                                            <td><span class="badge"><?php echo number_format($s->cnt); ?></span></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Migration Actions -->
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-play-circle"></i> Migration Actions</h3>
                    </div>
                    <div class="box-body">
                        <div class="row">
                            <div class="col-md-4">
                                <button class="btn btn-info btn-lg btn-block" id="btn-preview">
                                    <i class="fa fa-eye"></i> Preview Changes
                                </button>
                                <p class="text-muted text-center" style="margin-top:10px;">See what will be migrated</p>
                            </div>
                            <div class="col-md-4">
                                <button class="btn btn-warning btn-lg btn-block" id="btn-dry-run">
                                    <i class="fa fa-flask"></i> Dry Run
                                </button>
                                <p class="text-muted text-center" style="margin-top:10px;">Test without changes</p>
                            </div>
                            <div class="col-md-4">
                                <button class="btn btn-success btn-lg btn-block" id="btn-execute">
                                    <i class="fa fa-rocket"></i> Execute Migration
                                </button>
                                <p class="text-muted text-center" style="margin-top:10px;">Apply changes</p>
                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-md-6">
                                <a href="<?php echo base_url(); ?>app/opd_migration/generate_sql" class="btn btn-default btn-block">
                                    <i class="fa fa-download"></i> Download SQL Script
                                </a>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Batch Size:</label>
                                    <select id="batch-size" class="form-control">
                                        <option value="100">100 records</option>
                                        <option value="500" selected>500 records</option>
                                        <option value="1000">1000 records</option>
                                        <option value="5000">5000 records</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Preview Results -->
                <div class="box box-warning" id="preview-section" style="display:none;">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-list"></i> Preview Results</h3>
                        <span class="badge" id="preview-count">0</span>
                    </div>
                    <div class="box-body preview-table">
                        <table class="table table-striped table-condensed" id="preview-table">
                            <thead>
                                <tr>
                                    <th>OPD No</th>
                                    <th>Patient</th>
                                    <th>Date</th>
                                    <th>Legacy Status</th>
                                    <th>Current Workflow</th>
                                    <th>Issue</th>
                                    <th>Proposed</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>

                <!-- Migration Log -->
                <div class="box box-default" id="log-section" style="display:none;">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-terminal"></i> Migration Log</h3>
                    </div>
                    <div class="box-body">
                        <div class="migration-log" id="migration-log"></div>
                    </div>
                </div>

            </section>
        </aside>
    </div>

    <script src="<?php echo base_url(); ?>public/js/jquery.min.js"></script>
    <script src="<?php echo base_url(); ?>public/js/bootstrap.min.js"></script>
    <script src="<?php echo base_url(); ?>public/js/AdminLTE/app.js"></script>
    
    <script>
    var CSRF_TOKEN_NAME = '<?php echo $this->security->get_csrf_token_name(); ?>';
    var CSRF_COOKIE_NAME = '<?php echo $this->config->item('csrf_cookie_name'); ?>';
    function getCsrf() {
        var name = CSRF_COOKIE_NAME + '=';
        var parts = document.cookie.split(';');
        for (var i = 0; i < parts.length; i++) {
            var c = parts[i].trim();
            if (c.indexOf(name) === 0) return decodeURIComponent(c.substring(name.length));
        }
        return '';
    }
    $.ajaxSetup({ beforeSend: function(xhr, settings) {
        if (settings.type === 'POST') {
            var token = getCsrf();
            var sep = (settings.data && settings.data.length > 0) ? '&' : '';
            settings.data = (settings.data || '') + sep + encodeURIComponent(CSRF_TOKEN_NAME) + '=' + encodeURIComponent(token);
        }
    }});
    $(document).ready(function() {
        function log(msg) {
            var time = new Date().toLocaleTimeString();
            $('#migration-log').append('[' + time + '] ' + msg + '\n');
            $('#migration-log').scrollTop($('#migration-log')[0].scrollHeight);
        }

        $('#btn-preview').click(function() {
            var $btn = $(this);
            $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Loading...');
            
            $.ajax({
                url: '<?php echo base_url(); ?>app/opd_migration/preview',
                type: 'GET',
                data: { limit: $('#batch-size').val() },
                dataType: 'json',
                success: function(resp) {
                    $('#preview-section').show();
                    $('#preview-count').text(resp.count);
                    var tbody = $('#preview-table tbody').empty();
                    
                    resp.records.forEach(function(r) {
                        var issueClass = r.issue === 'MISSING_WORKFLOW' ? 'warning' : 'danger';
                        tbody.append(
                            '<tr>' +
                            '<td>' + r.IO_ID + '</td>' +
                            '<td>' + r.patient_no + '</td>' +
                            '<td>' + r.date_visit + '</td>' +
                            '<td><span class="label label-default">' + (r.legacy_status || '-') + '</span></td>' +
                            '<td><span class="label label-info">' + (r.workflow_status || '-') + '</span></td>' +
                            '<td><span class="label label-' + issueClass + '">' + r.issue + '</span></td>' +
                            '<td><span class="label label-success">' + r.proposed_status + '</span></td>' +
                            '</tr>'
                        );
                    });
                    
                    $btn.prop('disabled', false).html('<i class="fa fa-eye"></i> Preview Changes');
                },
                error: function() {
                    alert('Failed to load preview');
                    $btn.prop('disabled', false).html('<i class="fa fa-eye"></i> Preview Changes');
                }
            });
        });

        function runMigration(dryRun) {
            var btnId = dryRun ? '#btn-dry-run' : '#btn-execute';
            var $btn = $(btnId);
            var originalHtml = $btn.html();
            
            if (!dryRun && !confirm('Are you sure you want to execute the migration? This will modify the database.')) {
                return;
            }
            
            $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Running...');
            $('#log-section').show();
            log('Starting migration' + (dryRun ? ' (DRY RUN)' : '') + '...');
            
            $.ajax({
                url: '<?php echo base_url(); ?>app/opd_migration/execute',
                type: 'POST',
                data: { 
                    dry_run: dryRun ? '1' : '0',
                    batch_size: $('#batch-size').val()
                },
                dataType: 'json',
                success: function(resp) {
                    log('Created: ' + resp.created + ' workflow records');
                    log('Updated: ' + resp.updated + ' inconsistent records');
                    
                    if (resp.errors && resp.errors.length > 0) {
                        resp.errors.forEach(function(e) {
                            log('ERROR: ' + e);
                        });
                    }
                    
                    log('Migration ' + (dryRun ? '(DRY RUN) ' : '') + 'complete!');
                    
                    if (!dryRun) {
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    }
                    
                    $btn.prop('disabled', false).html(originalHtml);
                },
                error: function(xhr) {
                    log('ERROR: Migration request failed');
                    if (xhr.responseText) {
                        log(xhr.responseText.substring(0, 500));
                    }
                    $btn.prop('disabled', false).html(originalHtml);
                }
            });
        }

        $('#btn-dry-run').click(function() { runMigration(true); });
        $('#btn-execute').click(function() { runMigration(false); });
    });
    </script>
</body>
</html>
