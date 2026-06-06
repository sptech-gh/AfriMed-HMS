<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Radiology Critical Findings Definitions - Hebrew Medical Center</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
    <style>
        .severity-badge { font-size: 11px; padding: 3px 8px; border-radius: 3px; }
        .severity-LIFE_THREATENING { background: #dd4b39; color: #fff; }
        .severity-CRITICAL { background: #f39c12; color: #fff; }
        .severity-URGENT { background: #00c0ef; color: #fff; }
    </style>
</head>
<body class="skin-blue">
    <?php require_once(APPPATH.'views/include/header.php');?>
    <div class="wrapper row-offcanvas row-offcanvas-left">
        <?php require_once(APPPATH.'views/include/sidebar.php');?>
        <aside class="right-side">
            <section class="content-header">
                <h1><i class="fa fa-x-ray"></i> Radiology Critical Findings Definitions</h1>
                <ol class="breadcrumb">
                    <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                    <li><a href="<?php echo base_url()?>app/critical_alerts">Critical Alerts</a></li>
                    <li class="active">Radiology Findings</li>
                </ol>
            </section>

            <section class="content">
                <div class="row">
                    <div class="col-md-12">
                        <a href="<?php echo base_url();?>app/critical_alerts/radiology_alerts" class="btn btn-default" style="margin-bottom: 15px;">
                            <i class="fa fa-arrow-left"></i> Back to Radiology Alerts
                        </a>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <div class="box box-info">
                            <div class="box-header with-border">
                                <h3 class="box-title"><i class="fa fa-list"></i> Critical Findings Keywords</h3>
                            </div>
                            <div class="box-body">
                                <div class="alert alert-info">
                                    <i class="fa fa-info-circle"></i> 
                                    These keywords are automatically detected in radiology findings text. 
                                    When a match is found, a critical alert is generated.
                                </div>

                                <?php if (empty($findings)): ?>
                                    <div class="alert alert-warning">
                                        <i class="fa fa-warning"></i> No findings definitions found. The system will seed default values.
                                    </div>
                                <?php else: ?>
                                    <table class="table table-hover table-striped">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Finding Name</th>
                                                <th>Keywords</th>
                                                <th>Severity</th>
                                                <th>Description</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php $i = 1; foreach ($findings as $finding): ?>
                                                <tr>
                                                    <td><?php echo $i++; ?></td>
                                                    <td><strong><?php echo htmlspecialchars($finding->finding_name); ?></strong></td>
                                                    <td>
                                                        <?php 
                                                        $keywords = explode(',', $finding->keywords);
                                                        foreach ($keywords as $kw): 
                                                        ?>
                                                            <span class="label label-default"><?php echo trim(htmlspecialchars($kw)); ?></span>
                                                        <?php endforeach; ?>
                                                    </td>
                                                    <td>
                                                        <span class="severity-badge severity-<?php echo $finding->severity; ?>">
                                                            <?php echo str_replace('_', ' ', $finding->severity); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($finding->description ?? '-'); ?></td>
                                                    <td>
                                                        <?php if ($finding->is_active): ?>
                                                            <span class="label label-success">Active</span>
                                                        <?php else: ?>
                                                            <span class="label label-default">Inactive</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
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
</body>
</html>
