<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>System Audit Log - HMS</title>
    <meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>
    <link href="<?php echo base_url(); ?>public/bootstrap/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/font-awesome/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/dist/css/AdminLTE.css" rel="stylesheet" type="text/css" />
</head>
<body class="skin-blue">
<?php require_once(APPPATH.'views/include/header.php');?>
<div class="wrapper row-offcanvas row-offcanvas-left">
    <?php require_once(APPPATH.'views/include/sidebar.php');?>
    <aside class="right-side">
        <section class="content-header">
            <h1><i class="fa fa-history"></i> System Audit Log</h1>
        </section>
        <section class="content">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Recent Activity (Last 500 entries)</h3>
                </div>
                <div class="box-body">
                    <table class="table table-striped table-condensed">
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>Type</th>
                                <th>Module</th>
                                <th>Action</th>
                                <th>User</th>
                                <th>Patient</th>
                                <th>Summary</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($logs)): foreach ($logs as $log): ?>
                            <tr>
                                <td><small><?php echo date('Y-m-d H:i', strtotime($log->created_at)); ?></small></td>
                                <td><span class="label label-info"><?php echo $log->audit_type; ?></span></td>
                                <td><?php echo $log->module; ?></td>
                                <td><code><?php echo $log->action; ?></code></td>
                                <td><?php echo $log->username; ?></td>
                                <td><?php echo $log->patient_no; ?></td>
                                <td><small><?php echo htmlspecialchars(substr($log->change_summary, 0, 100)); ?></small></td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </aside>
</div>
<script src="<?php echo base_url(); ?>public/jquery/jquery.min.js"></script>
<script src="<?php echo base_url(); ?>public/bootstrap/js/bootstrap.min.js"></script>
<script src="<?php echo base_url(); ?>public/dist/js/app.js"></script>
</body>
</html>
