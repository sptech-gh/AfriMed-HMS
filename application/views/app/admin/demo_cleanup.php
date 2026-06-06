<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Demo Database Cleanup - Hebrew Medical Center</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <link href="<?php echo base_url(); ?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
    <style>
        .cleanup-warning {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a5a 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .cleanup-warning h3 {
            margin-top: 0;
        }
        .table-category {
            background: #f8f9fa;
            font-weight: bold;
            color: #333;
        }
        .record-count {
            font-weight: bold;
        }
        .record-count.has-data {
            color: #d9534f;
        }
        .record-count.empty {
            color: #5cb85c;
        }
        .keep-badge {
            background: #5cb85c;
            color: white;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
        }
        .clean-badge {
            background: #d9534f;
            color: white;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
        }
        #cleanup-log {
            max-height: 400px;
            overflow-y: auto;
            background: #1a1a2e;
            color: #0f0;
            padding: 15px;
            font-family: monospace;
            font-size: 12px;
            border-radius: 4px;
        }
        #cleanup-log .log-entry {
            margin-bottom: 5px;
        }
        #cleanup-log .log-time {
            color: #888;
        }
        #cleanup-log .log-error {
            color: #ff6b6b;
        }
        .progress-section {
            display: none;
        }
        .summary-box {
            text-align: center;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        .summary-box.to-clean {
            background: #fff3cd;
            border: 2px solid #ffc107;
        }
        .summary-box.to-keep {
            background: #d4edda;
            border: 2px solid #28a745;
        }
        .summary-box h2 {
            margin: 0;
            font-size: 48px;
        }
        .confirmation-input {
            font-size: 18px;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
    </style>
</head>
<body class="skin-blue">
    <?php require_once(APPPATH . 'views/include/header.php'); ?>
    
    <div class="wrapper row-offcanvas row-offcanvas-left">
        <?php require_once(APPPATH . 'views/include/sidebar.php'); ?>
        
        <aside class="right-side">
            <section class="content-header">
                <h1>
                    <i class="fa fa-database"></i> Demo Database Cleanup
                    <small>Prepare fresh demo environment</small>
                </h1>
                <ol class="breadcrumb">
                    <li><a href="<?php echo base_url(); ?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                    <li class="active">Demo Cleanup</li>
                </ol>
            </section>

            <section class="content">
                <!-- Warning Banner -->
                <div class="cleanup-warning">
                    <h3><i class="fa fa-exclamation-triangle"></i> WARNING: Destructive Operation</h3>
                    <p>This tool will <strong>permanently delete</strong> all patient data, billing records, lab results, and clinical data from the database.</p>
                    <p><strong>This action cannot be undone!</strong> Make sure you have a backup before proceeding.</p>
                </div>

                <!-- Summary Boxes -->
                <div class="row">
                    <div class="col-md-4">
                        <div class="summary-box to-clean">
                            <h2><?php echo count($tables_to_clean); ?></h2>
                            <p><span class="clean-badge">TO CLEAN</span> Tables with patient/transaction data</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="summary-box to-keep">
                            <h2><?php echo count($tables_to_keep); ?></h2>
                            <p><span class="keep-badge">TO KEEP</span> Master/Config tables preserved</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="summary-box" style="background:#e3f2fd;border:2px solid #2196f3;">
                            <h2><?php echo array_sum(array_filter($record_counts, 'is_numeric')); ?></h2>
                            <p><i class="fa fa-trash"></i> Total records to be deleted</p>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="box box-danger" id="action-section">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-cogs"></i> Cleanup Actions</h3>
                    </div>
                    <div class="box-body">
                        <div class="row">
                            <div class="col-md-4">
                                <button class="btn btn-info btn-lg btn-block" id="btn-backup">
                                    <i class="fa fa-download"></i> Create Backup First
                                </button>
                                <p class="text-muted text-center" style="margin-top:10px;">Recommended before cleanup</p>
                            </div>
                            <div class="col-md-4">
                                <button class="btn btn-warning btn-lg btn-block" id="btn-dry-run">
                                    <i class="fa fa-search"></i> Dry Run (Preview)
                                </button>
                                <p class="text-muted text-center" style="margin-top:10px;">Preview counts + verify tables</p>
                            </div>
                            <div class="col-md-4">
                                <button class="btn btn-danger btn-lg btn-block" id="btn-show-confirm">
                                    <i class="fa fa-trash"></i> Execute Cleanup Now
                                </button>
                                <p class="text-muted text-center" style="margin-top:10px;">Requires confirmation</p>
                            </div>
                        </div>
						<div class="row" style="margin-top:15px;">
							<div class="col-md-12 text-center">
								<a href="<?php echo base_url(); ?>app/demo_cleanup/generate_sql" class="btn btn-default" style="margin-right:10px;">
									<i class="fa fa-file-code-o"></i> Download SQL Script
								</a>
								<button class="btn btn-default" id="btn-backup-help">
									<i class="fa fa-info-circle"></i> Backup Instructions
								</button>
							</div>
						</div>
                    </div>
                </div>

				<!-- Dry Run Results (Hidden by default) -->
				<div class="box box-warning" id="dryrun-section" style="display:none;">
					<div class="box-header with-border">
						<h3 class="box-title"><i class="fa fa-search"></i> Dry Run Preview</h3>
						<div class="box-tools pull-right">
							<button class="btn btn-box-tool" id="btn-dryrun-close"><i class="fa fa-times"></i></button>
						</div>
					</div>
					<div class="box-body">
						<p class="text-muted">This does not delete anything. It only checks table existence and row counts.</p>
						<div class="row" id="dryrun-scope" style="display:none;">
							<div class="col-md-12">
								<table class="table table-bordered table-condensed">
									<thead>
										<tr>
											<th style="width:200px;">Scope</th>
											<th>Impact</th>
										</tr>
									</thead>
									<tbody>
										<tr><td><strong>Patients</strong></td><td id="scope-patients"></td></tr>
										<tr><td><strong>Clinical</strong></td><td id="scope-clinical"></td></tr>
										<tr><td><strong>Billing</strong></td><td id="scope-billing"></td></tr>
									</tbody>
								</table>
							</div>
						</div>
						<div class="alert alert-info" id="dryrun-summary" style="display:none;"></div>
						<div class="table-responsive">
							<table class="table table-striped table-condensed" id="dryrun-table">
								<thead>
									<tr>
										<th>Table</th>
										<th>Exists</th>
										<th>Rows</th>
									</tr>
								</thead>
								<tbody></tbody>
							</table>
						</div>
					</div>
				</div>

				<!-- Backup Instructions Modal -->
				<div class="modal fade" id="backupHelpModal" tabindex="-1" role="dialog" aria-hidden="true">
					<div class="modal-dialog modal-lg">
						<div class="modal-content">
							<div class="modal-header">
								<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
								<h4 class="modal-title"><i class="fa fa-info-circle"></i> Backup Instructions</h4>
							</div>
							<div class="modal-body">
								<p><strong>Option A (Recommended): Use the built-in backup button</strong></p>
								<p>Click <code>Create Backup First</code>. The file will be written to:</p>
								<p><code><?php echo FCPATH; ?>backups\</code></p>
								<hr>
								<p><strong>Option B: MySQL command-line (mysqldump)</strong></p>
								<p class="text-muted">Run in a terminal on the server machine (Laragon typically uses user <code>root</code> with empty password).</p>
								<pre>mysqldump -u root --databases hms_master &gt; hms_master_backup.sql</pre>
								<p class="text-muted">If you use a password:</p>
								<pre>mysqldump -u root -p --databases hms_master &gt; hms_master_backup.sql</pre>
								<hr>
								<p><strong>Option C: phpMyAdmin</strong></p>
								<p>Go to phpMyAdmin &gt; select database <code>hms_master</code> &gt; Export &gt; Quick/Custom &gt; Go.</p>
								<hr>
								<p><strong>Manual Cleanup Script</strong></p>
								<p>You can download a SQL cleanup script and run it manually:</p>
								<p><a href="<?php echo base_url(); ?>app/demo_cleanup/generate_sql" class="btn btn-default"><i class="fa fa-file-code-o"></i> Download SQL Script</a></p>
							</div>
							<div class="modal-footer">
								<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
							</div>
						</div>
					</div>
				</div>

                <!-- Confirmation Section (Hidden by default) -->
                <div class="box box-danger" id="confirm-section" style="display:none;">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-exclamation-circle"></i> Confirm Cleanup</h3>
                    </div>
                    <div class="box-body text-center">
                        <p class="lead">To confirm deletion of all patient and transaction data, type:</p>
                        <p><code style="font-size:24px;">CLEAN_DEMO_DATABASE</code></p>
                        <div class="form-group" style="max-width:400px;margin:20px auto;">
                            <input type="text" id="confirm-input" class="form-control confirmation-input" placeholder="Type confirmation here">
                        </div>
                        <button class="btn btn-danger btn-lg" id="btn-execute" disabled>
                            <i class="fa fa-trash"></i> Execute Cleanup
                        </button>
                        <button class="btn btn-default btn-lg" id="btn-cancel">
                            <i class="fa fa-times"></i> Cancel
                        </button>
                    </div>
                </div>

                <!-- Progress Section (Hidden by default) -->
                <div class="box box-primary progress-section" id="progress-section">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-spinner fa-spin"></i> Cleanup Progress</h3>
                    </div>
                    <div class="box-body">
                        <div class="progress progress-striped active">
                            <div class="progress-bar progress-bar-danger" id="cleanup-progress" style="width: 0%"></div>
                        </div>
                        <div id="cleanup-log"></div>
                    </div>
                </div>

                <!-- Results Section (Hidden by default) -->
                <div class="box box-success" id="results-section" style="display:none;">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-check-circle"></i> Cleanup Complete</h3>
                    </div>
                    <div class="box-body text-center">
                        <i class="fa fa-check-circle text-success" style="font-size:80px;"></i>
                        <h2>Demo Database Ready!</h2>
                        <p class="lead" id="results-summary"></p>
                        <a href="<?php echo base_url(); ?>app/dashboard" class="btn btn-primary btn-lg">
                            <i class="fa fa-dashboard"></i> Go to Dashboard
                        </a>
                    </div>
                </div>

                <!-- Tables to Clean -->
                <div class="box box-danger collapsed-box">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-trash"></i> Tables to Clean (<?php echo count($tables_to_clean); ?>)</h3>
                        <div class="box-tools pull-right">
                            <button class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-plus"></i></button>
                        </div>
                    </div>
                    <div class="box-body">
                        <table class="table table-striped table-condensed">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Table Name</th>
                                    <th>Records</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $i = 1; foreach ($tables_to_clean as $table): ?>
                                <tr>
                                    <td><?php echo $i++; ?></td>
                                    <td><code><?php echo $table; ?></code></td>
                                    <td class="record-count <?php echo (isset($record_counts[$table]) && $record_counts[$table] > 0) ? 'has-data' : 'empty'; ?>">
                                        <?php echo isset($record_counts[$table]) ? number_format($record_counts[$table]) : 'N/A'; ?>
                                    </td>
                                    <td><span class="clean-badge">WILL CLEAN</span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Tables to Keep -->
                <div class="box box-success collapsed-box">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-shield"></i> Tables to Keep (<?php echo count($tables_to_keep); ?>)</h3>
                        <div class="box-tools pull-right">
                            <button class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-plus"></i></button>
                        </div>
                    </div>
                    <div class="box-body">
                        <table class="table table-striped table-condensed">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Table Name</th>
                                    <th>Category</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $i = 1; foreach ($tables_to_keep as $table): ?>
                                <tr>
                                    <td><?php echo $i++; ?></td>
                                    <td><code><?php echo $table; ?></code></td>
                                    <td><span class="keep-badge">PRESERVED</span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
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
    // IMPORTANT: cookie_httponly is enabled in this project, so JS cannot reliably
    // read the CSRF cookie. Embed the hash server-side and send it with AJAX.
    var CSRF_HASH = '<?php echo $this->security->get_csrf_hash(); ?>';
    function getCsrf() {
        return CSRF_HASH || '';
    }
    $.ajaxSetup({ beforeSend: function(xhr, settings) {
        if (settings.type === 'POST') {
            var token = getCsrf();
			if (token) {
				// jQuery can send data as string, object, or FormData. Handle all.
				if (typeof FormData !== 'undefined' && settings.data instanceof FormData) {
					settings.data.append(CSRF_TOKEN_NAME, token);
				} else if (typeof settings.data === 'string') {
					var sep = (settings.data && settings.data.length > 0) ? '&' : '';
					settings.data = (settings.data || '') + sep + encodeURIComponent(CSRF_TOKEN_NAME) + '=' + encodeURIComponent(token);
				} else if (typeof settings.data === 'object') {
					settings.data = settings.data || {};
					settings.data[CSRF_TOKEN_NAME] = token;
				} else {
					settings.data = {};
					settings.data[CSRF_TOKEN_NAME] = token;
				}
			}
        }
    }});
    $(document).ready(function() {
        // Show confirmation section
        $('#btn-show-confirm').click(function() {
            $('#action-section').slideUp();
            $('#confirm-section').slideDown();
            $('#confirm-input').focus();
        });

		// Backup instructions
		$('#btn-backup-help').click(function() {
			$('#backupHelpModal').modal('show');
		});

		// Dry run preview
		$('#btn-dry-run').click(function() {
			var $btn = $(this);
			$btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Loading...');
			$('#dryrun-section').slideDown();
			$('#dryrun-scope').hide();
			$('#dryrun-summary').hide().text('');
			$('#dryrun-table tbody').html('');
			$.ajax({
				url: '<?php echo base_url(); ?>app/demo_cleanup/dry_run',
				type: 'GET',
				dataType: 'json',
				success: function(resp) {
					if (resp && resp.status === 'success') {
						if (resp.scope_summary) {
							var p = resp.scope_summary.Patients || 'N/A';
							var c = resp.scope_summary.Clinical || 'N/A';
							var b = resp.scope_summary.Billing || 'N/A';
							$('#scope-patients').html(p === 'YES' ? '<span class="label label-danger">YES</span>' : '<span class="label label-success">NO</span>');
							$('#scope-clinical').html(c === 'YES' ? '<span class="label label-danger">YES</span>' : '<span class="label label-success">NO</span>');
							$('#scope-billing').html(b === 'YES' ? '<span class="label label-danger">YES</span>' : '<span class="label label-success">NO</span>');
							$('#dryrun-scope').show();
						}
						var total = resp.total_records_to_delete || 0;
						$('#dryrun-summary').show().html(
							'<strong>Database:</strong> ' + (resp.database || 'N/A') +
							' &nbsp; | &nbsp; <strong>Tables to clean:</strong> ' + (resp.tables_to_clean ? resp.tables_to_clean.length : 0) +
							' &nbsp; | &nbsp; <strong>Total rows to delete:</strong> ' + total
						);
						var rows = [];
						var tables = resp.tables_to_clean || [];
						for (var i=0;i<tables.length;i++) {
							var t = tables[i];
							var ex = resp.exists && typeof resp.exists[t] !== 'undefined' ? resp.exists[t] : 0;
							var cnt = resp.record_counts && typeof resp.record_counts[t] !== 'undefined' ? resp.record_counts[t] : 'N/A';
							rows.push({table:t, exists:ex, count:cnt});
						}
						// Sort numeric counts desc to surface biggest tables
						rows.sort(function(a,b){
							var an = (typeof a.count === 'number') ? a.count : (isNaN(parseInt(a.count,10)) ? -1 : parseInt(a.count,10));
							var bn = (typeof b.count === 'number') ? b.count : (isNaN(parseInt(b.count,10)) ? -1 : parseInt(b.count,10));
							return bn - an;
						});
						var html='';
						for (var j=0;j<rows.length;j++) {
							var r = rows[j];
							html += '<tr>'+
								'<td><code>'+ r.table +'</code></td>'+
								'<td>' + (r.exists ? '<span class="label label-success">YES</span>' : '<span class="label label-default">NO</span>') + '</td>'+
								'<td>' + r.count + '</td>'+
							'</tr>';
						}
						$('#dryrun-table tbody').html(html);
					} else {
						alert('Dry run failed.');
					}
				},
				error: function(xhr) {
					var msg = 'Dry run request failed.';
					if (xhr.responseText) {
						msg += '\n\nServer response: ' + xhr.responseText.substring(0, 500);
					}
					alert(msg);
				},
				complete: function() {
					$btn.prop('disabled', false).html('<i class="fa fa-search"></i> Dry Run (Preview)');
				}
			});
		});

		$('#btn-dryrun-close').click(function(){
			$('#dryrun-section').slideUp();
		});

        // Cancel confirmation
        $('#btn-cancel').click(function() {
            $('#confirm-section').slideUp();
            $('#action-section').slideDown();
            $('#confirm-input').val('');
        });

        // Enable/disable execute button based on confirmation input
        $('#confirm-input').on('input', function() {
            var val = $(this).val().toUpperCase();
            $('#btn-execute').prop('disabled', val !== 'CLEAN_DEMO_DATABASE');
        });

        // Create backup
        $('#btn-backup').click(function() {
            var $btn = $(this);
            $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Creating Backup...');
            
            $.ajax({
                url: '<?php echo base_url(); ?>app/demo_cleanup/backup',
                type: 'POST',
                dataType: 'json',
                data: {},
                success: function(resp) {
                    if (resp.status === 'success') {
                        var sizeStr = resp.size > 1048576 ? (resp.size / 1048576).toFixed(2) + ' MB' : (resp.size / 1024).toFixed(2) + ' KB';
                        alert('Backup created successfully!\nFile: ' + resp.file + '\nSize: ' + sizeStr + '\nTables: ' + (resp.tables || 'N/A') + '\nRows: ' + (resp.rows || 'N/A'));
                    } else {
                        alert('Backup failed: ' + resp.message);
                    }
                    $btn.prop('disabled', false).html('<i class="fa fa-download"></i> Create Backup First');
                },
                error: function(xhr, status, error) {
                    var msg = 'Backup request failed. Please backup manually.';
                    if (xhr.responseText) {
                        msg += '\n\nServer response: ' + xhr.responseText.substring(0, 500);
                    }
                    alert(msg);
                    $btn.prop('disabled', false).html('<i class="fa fa-download"></i> Create Backup First');
                }
            });
        });

        // Execute cleanup
        $('#btn-execute').click(function() {
            var $btn = $(this);
            $btn.prop('disabled', true);
            
            $('#confirm-section').slideUp();
            $('#progress-section').slideDown();
            
            // Simulate progress
            var progress = 0;
            var progressInterval = setInterval(function() {
                progress += Math.random() * 15;
                if (progress > 90) progress = 90;
                $('#cleanup-progress').css('width', progress + '%');
            }, 200);

            $.ajax({
                url: '<?php echo base_url(); ?>app/demo_cleanup/execute',
                type: 'POST',
                data: { confirm: 'CLEAN_DEMO_DATABASE' },
                dataType: 'json',
                timeout: 300000, // 5 minutes timeout for large databases
                success: function(resp) {
                    clearInterval(progressInterval);
                    $('#cleanup-progress').css('width', '100%');
                    
                    // Display log
                    var logHtml = '';
                    if (resp.log) {
                        resp.log.forEach(function(entry) {
                            var cssClass = entry.message.indexOf('ERROR') >= 0 ? 'log-error' : '';
                            logHtml += '<div class="log-entry ' + cssClass + '"><span class="log-time">[' + entry.time + ']</span> ' + entry.message + '</div>';
                        });
                    }
                    $('#cleanup-log').html(logHtml);
                    
                    if (resp.status === 'success') {
                        setTimeout(function() {
                            $('#progress-section').slideUp();
                            $('#results-section').slideDown();
                            $('#results-summary').text('Cleaned ' + resp.cleaned_tables + ' tables. Skipped ' + resp.skipped_tables + ' non-existent tables.');
                        }, 1000);
                    } else {
                        alert('Cleanup failed: ' + resp.message);
                    }
                },
                error: function(xhr, status, error) {
                    clearInterval(progressInterval);
                    var msg = 'Cleanup request failed. Please check server logs.';
                    if (xhr.responseText) {
                        msg += '\n\nServer response: ' + xhr.responseText.substring(0, 500);
                    }
                    alert(msg);
                }
            });
        });
    });
    </script>
</body>
</html>
