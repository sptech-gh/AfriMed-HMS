<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title>Hebrew Medical Center</title>
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
                    <h1><i class="fa fa-warning"></i> Orphaned Lab Requests</h1>
                    <ol class="breadcrumb">
                        <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                        <li><a href="<?php echo base_url()?>app/laboratory/lab_queue">Lab Queue</a></li>
                        <li class="active">Orphaned</li>
                    </ol>
                </section>

                <section class="content">
                    <?php echo isset($message) && $message ? $message : ''; ?>

                    <div class="box">
                        <div class="box-header">
                            <div class="box-tools pull-right">
                                <a href="<?php echo base_url();?>app/laboratory/lab_queue" class="btn btn-sm btn-default"><i class="fa fa-arrow-left"></i> Back to Queue</a>
                            </div>
                        </div>
                        <div class="box-body table-responsive no-padding">
                            <table class="table table-hover table-striped">
                                <thead>
                                    <tr>
                                        <th>Lab ID</th>
                                        <th>Visit ID</th>
                                        <th>Date</th>
                                        <th>Patient No</th>
                                        <th>Patient Name</th>
                                        <th>Category ID</th>
                                        <th>Laboratory ID</th>
                                        <th>Laboratory Text</th>
                                        <th>Result</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if (isset($rows) && is_array($rows) && count($rows) > 0): ?>
                                    <?php foreach ($rows as $r): ?>
                                        <tr>
                                            <td><?php echo (int)$r->io_lab_id; ?></td>
                                            <td><?php echo htmlspecialchars((string)$r->iop_id, ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars((string)$r->dDate, ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars(isset($r->patient_no) ? (string)$r->patient_no : '', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars(isset($r->patient_name) ? (string)$r->patient_name : '', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars((string)$r->category_id, ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars((string)$r->laboratory_id, ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars((string)$r->laboratory_text, ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars((string)$r->result, ENT_QUOTES, 'UTF-8'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="9" class="text-center text-muted">No orphaned records found.</td></tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="box-footer clearfix">
                            <div class="pull-left">
                                <?php echo isset($total) ? (int)$total : 0; ?> record(s)
                            </div>
                            <div class="pull-right">
                                <?php echo isset($pagination) ? $pagination : ''; ?>
                            </div>
                        </div>
                    </div>
                </section>
            </aside>
        </div>

        <script src="<?php echo base_url()?>public/js/jquery.min.js"></script>
        <script src="<?php echo base_url()?>public/js/bootstrap.min.js"></script>
    </body>
</html>
