<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Escalation Configuration - Hebrew Medical Center</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
    <style>
        .level-badge { padding: 5px 12px; border-radius: 4px; font-weight: bold; }
        .level-1 { background: #3c8dbc; color: #fff; }
        .level-2 { background: #f39c12; color: #fff; }
        .level-3 { background: #dd4b39; color: #fff; }
        .level-4 { background: #111; color: #fff; }
        .config-card { border: 1px solid #ddd; border-radius: 8px; padding: 20px; margin-bottom: 15px; }
        .config-card.inactive { opacity: 0.6; background: #f9f9f9; }
    </style>
</head>
<body class="skin-blue">
    <?php require_once(APPPATH.'views/include/header.php');?>
    <div class="wrapper row-offcanvas row-offcanvas-left">
        <?php require_once(APPPATH.'views/include/sidebar.php');?>
        <aside class="right-side">
            <section class="content-header">
                <h1><i class="fa fa-cogs"></i> Escalation Configuration</h1>
                <ol class="breadcrumb">
                    <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                    <li><a href="<?php echo base_url()?>app/critical_alerts">Critical Alerts</a></li>
                    <li class="active">Escalation Config</li>
                </ol>
            </section>

            <section class="content">
                <div class="row">
                    <div class="col-md-12">
                        <a href="<?php echo base_url();?>app/critical_alerts" class="btn btn-default" style="margin-bottom: 15px;">
                            <i class="fa fa-arrow-left"></i> Back to Dashboard
                        </a>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <div class="box box-primary">
                            <div class="box-header with-border">
                                <h3 class="box-title"><i class="fa fa-sitemap"></i> Escalation Chain Configuration</h3>
                            </div>
                            <div class="box-body">
                                <div class="alert alert-info">
                                    <i class="fa fa-info-circle"></i> 
                                    Configure the escalation chain for unacknowledged critical alerts. 
                                    Alerts will automatically escalate to the next level after the specified timeout.
                                </div>

                                <?php if (empty($configs)): ?>
                                    <div class="alert alert-warning">
                                        <i class="fa fa-warning"></i> No escalation configuration found. The system will seed default values on next page load.
                                    </div>
                                <?php else: ?>
                                    <div class="row">
                                        <?php foreach ($configs as $config): ?>
                                            <div class="col-md-6">
                                                <div class="config-card <?php echo $config->is_active ? '' : 'inactive'; ?>">
                                                    <div class="row">
                                                        <div class="col-xs-8">
                                                            <span class="level-badge level-<?php echo $config->escalation_level; ?>">
                                                                Level <?php echo $config->escalation_level; ?>
                                                            </span>
                                                            <h4 style="margin-top: 10px;"><?php echo ucwords(str_replace('_', ' ', $config->role_name)); ?></h4>
                                                        </div>
                                                        <div class="col-xs-4 text-right">
                                                            <label class="switch">
                                                                <input type="checkbox" class="config-active" 
                                                                       data-id="<?php echo $config->config_id; ?>"
                                                                       <?php echo $config->is_active ? 'checked' : ''; ?>>
                                                                <span class="text-muted"><?php echo $config->is_active ? 'Active' : 'Inactive'; ?></span>
                                                            </label>
                                                        </div>
                                                    </div>
                                                    <hr>
                                                    <div class="form-group">
                                                        <label>Timeout (minutes)</label>
                                                        <input type="number" class="form-control config-timeout" 
                                                               data-id="<?php echo $config->config_id; ?>"
                                                               value="<?php echo $config->timeout_minutes; ?>" min="1" max="1440">
                                                        <small class="text-muted">Time before escalating to this level</small>
                                                    </div>
                                                    <div class="form-group">
                                                        <label>Notification Method</label>
                                                        <select class="form-control config-method" data-id="<?php echo $config->config_id; ?>">
                                                            <option value="SYSTEM" <?php echo $config->notification_method === 'SYSTEM' ? 'selected' : ''; ?>>System Only</option>
                                                            <option value="SMS" <?php echo $config->notification_method === 'SMS' ? 'selected' : ''; ?>>SMS</option>
                                                            <option value="EMAIL" <?php echo $config->notification_method === 'EMAIL' ? 'selected' : ''; ?>>Email</option>
                                                            <option value="ALL" <?php echo $config->notification_method === 'ALL' ? 'selected' : ''; ?>>All Methods</option>
                                                        </select>
                                                    </div>
                                                    <button class="btn btn-primary btn-sm btn-save" data-id="<?php echo $config->config_id; ?>">
                                                        <i class="fa fa-save"></i> Save Changes
                                                    </button>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Escalation Flow Diagram -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="box box-default">
                            <div class="box-header with-border">
                                <h3 class="box-title"><i class="fa fa-flow-chart"></i> Escalation Flow</h3>
                            </div>
                            <div class="box-body text-center">
                                <div class="row">
                                    <div class="col-md-2 col-md-offset-1">
                                        <div class="well">
                                            <i class="fa fa-bell fa-2x text-red"></i><br>
                                            <strong>Critical Alert</strong><br>
                                            <small>Created</small>
                                        </div>
                                    </div>
                                    <div class="col-md-1" style="padding-top: 40px;">
                                        <i class="fa fa-arrow-right fa-2x text-muted"></i>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="well" style="border-color: #3c8dbc;">
                                            <span class="level-badge level-1">L1</span><br>
                                            <strong>Ordering Doctor</strong><br>
                                            <small>15 min timeout</small>
                                        </div>
                                    </div>
                                    <div class="col-md-1" style="padding-top: 40px;">
                                        <i class="fa fa-arrow-right fa-2x text-muted"></i>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="well" style="border-color: #f39c12;">
                                            <span class="level-badge level-2">L2</span><br>
                                            <strong>Dept Head</strong><br>
                                            <small>30 min timeout</small>
                                        </div>
                                    </div>
                                    <div class="col-md-1" style="padding-top: 40px;">
                                        <i class="fa fa-arrow-right fa-2x text-muted"></i>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="well" style="border-color: #dd4b39;">
                                            <span class="level-badge level-3">L3</span><br>
                                            <strong>Medical Director</strong><br>
                                            <small>60 min timeout</small>
                                        </div>
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
    <script src="<?php echo base_url();?>public/js/bootstrap.min.js"></script>
    <script src="<?php echo base_url();?>public/js/AdminLTE/app.js"></script>
    <script>
        var csrfName = '<?php echo $this->security->get_csrf_token_name(); ?>';
        var csrfHash = '<?php echo $this->security->get_csrf_hash(); ?>';
        $(document).ready(function() {
            $('.btn-save').click(function() {
                var configId = $(this).data('id');
                var card = $(this).closest('.config-card');
                var timeout = card.find('.config-timeout').val();
                var method = card.find('.config-method').val();
                var isActive = card.find('.config-active').is(':checked') ? 1 : 0;

                var configData = {
                    config_id: configId,
                    timeout_minutes: timeout,
                    notification_method: method,
                    is_active: isActive
                };
                configData[csrfName] = csrfHash;
                $.post('<?php echo base_url();?>app/critical_alerts/save_escalation_config', configData, function(response) {
                    var res = typeof response === 'string' ? JSON.parse(response) : response;
                    if (res.success) {
                        alert('Configuration saved successfully');
                        location.reload();
                    } else {
                        alert('Error: ' + res.message);
                    }
                });
            });

            $('.config-active').change(function() {
                var card = $(this).closest('.config-card');
                if ($(this).is(':checked')) {
                    card.removeClass('inactive');
                    $(this).next('span').text('Active');
                } else {
                    card.addClass('inactive');
                    $(this).next('span').text('Inactive');
                }
            });
        });
    </script>
</body>
</html>
