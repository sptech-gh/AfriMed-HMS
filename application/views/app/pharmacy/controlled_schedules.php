<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Controlled Schedules | Hospital Management System</title>
    <meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>
    <link href="<?php echo base_url(); ?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/css/datatables/dataTables.bootstrap.css" rel="stylesheet" type="text/css" />
</head>
<body class="skin-blue">
<?php require_once(APPPATH.'views/include/header.php');?>

<div class="wrapper row-offcanvas row-offcanvas-left">
    <?php require_once(APPPATH.'views/include/sidebar.php');?>
    
    <aside class="right-side">
    <section class="content-header">
        <h1><i class="fa fa-list-ol"></i> Controlled Drug Schedules</h1>
        <ol class="breadcrumb">
            <li><a href="<?= base_url() ?>app/pharmacy"><i class="fa fa-medkit"></i> Pharmacy</a></li>
            <li><a href="<?= base_url() ?>app/pharmacy/controlled_drugs">Controlled Drugs</a></li>
            <li class="active">Schedules</li>
        </ol>
    </section>

    <section class="content">
        <?= isset($message) ? $message : '' ?>

        <div class="callout callout-info">
            <h4><i class="fa fa-info-circle"></i> About Drug Schedules</h4>
            <p>Drug schedules classify controlled substances based on their medical use and potential for abuse. Higher schedules (I, II) have stricter controls including double authentication and witness requirements.</p>
        </div>

        <!-- Schedules Table -->
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-list"></i> Drug Schedules (<?= count($schedules) ?>)</h3>
            </div>
            <div class="box-body">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Schedule</th>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Requirements</th>
                            <th>Max Days Supply</th>
                            <th>Audit Frequency</th>
                            <th>Storage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($schedules as $s): 
                            $scheduleClass = array(
                                'SCHEDULE_I' => 'danger',
                                'SCHEDULE_II' => 'danger',
                                'SCHEDULE_III' => 'warning',
                                'SCHEDULE_IV' => 'info',
                                'SCHEDULE_V' => 'default'
                            );
                            $cls = isset($scheduleClass[$s->schedule_code]) ? $scheduleClass[$s->schedule_code] : 'default';
                        ?>
                        <tr>
                            <td>
                                <span class="label label-<?= $cls ?>" style="font-size: 14px;">
                                    <?= htmlspecialchars($s->schedule_code) ?>
                                </span>
                            </td>
                            <td><strong><?= htmlspecialchars($s->schedule_name) ?></strong></td>
                            <td><?= htmlspecialchars($s->description) ?></td>
                            <td>
                                <?php if ($s->requires_double_auth): ?>
                                    <span class="label label-danger"><i class="fa fa-users"></i> Double Auth</span>
                                <?php endif; ?>
                                <?php if ($s->requires_witness): ?>
                                    <span class="label label-warning"><i class="fa fa-eye"></i> Witness</span>
                                <?php endif; ?>
                                <?php if ($s->requires_id_verification): ?>
                                    <span class="label label-info"><i class="fa fa-id-card"></i> ID Check</span>
                                <?php endif; ?>
                                <?php if ($s->requires_prescription_original): ?>
                                    <span class="label label-primary"><i class="fa fa-file-text"></i> Original Rx</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($s->max_days_supply > 0): ?>
                                    <?= $s->max_days_supply ?> days
                                <?php else: ?>
                                    <span class="text-danger">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                Every <?= $s->audit_frequency_days ?> days
                            </td>
                            <td><small><?= htmlspecialchars($s->storage_requirements) ?></small></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Requirements Legend -->
        <div class="box box-info">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-key"></i> Requirements Legend</h3>
            </div>
            <div class="box-body">
                <div class="row">
                    <div class="col-md-3">
                        <span class="label label-danger"><i class="fa fa-users"></i> Double Auth</span>
                        <p class="text-muted small">Requires authorization from two different pharmacists before dispensing</p>
                    </div>
                    <div class="col-md-3">
                        <span class="label label-warning"><i class="fa fa-eye"></i> Witness</span>
                        <p class="text-muted small">A witness must be present during dispensing and sign the register</p>
                    </div>
                    <div class="col-md-3">
                        <span class="label label-info"><i class="fa fa-id-card"></i> ID Check</span>
                        <p class="text-muted small">Patient identity must be verified with valid ID before dispensing</p>
                    </div>
                    <div class="col-md-3">
                        <span class="label label-primary"><i class="fa fa-file-text"></i> Original Rx</span>
                        <p class="text-muted small">Original prescription document required (no copies/refills)</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Navigation -->
        <div class="row">
            <div class="col-md-6">
                <a href="<?= base_url() ?>app/pharmacy/controlled_drugs" class="btn btn-block btn-default">
                    <i class="fa fa-shield"></i> Controlled Drugs List
                </a>
            </div>
            <div class="col-md-6">
                <a href="<?= base_url() ?>app/pharmacy" class="btn btn-block btn-default">
                    <i class="fa fa-arrow-left"></i> Back to Worklist
                </a>
            </div>
        </div>
    </section>
</div>
    </aside>
</div>

<script src="<?php echo base_url(); ?>public/js/jquery.min.js"></script>
<script src="<?php echo base_url(); ?>public/js/bootstrap.min.js"></script>
<script src="<?php echo base_url(); ?>public/js/plugins/datatables/jquery.dataTables.js"></script>
<script src="<?php echo base_url(); ?>public/js/plugins/datatables/dataTables.bootstrap.js"></script>
<script src="<?php echo base_url(); ?>public/js/AdminLTE/app.js"></script>
</body>
</html>
