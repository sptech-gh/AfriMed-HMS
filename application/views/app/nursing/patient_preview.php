<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo isset($companyInfo->site_title) ? $companyInfo->site_title : 'HMS'; ?> — Patient Preview</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
</head>
<body class="skin-blue">
    <?php require_once(APPPATH.'views/include/header.php');?>
    <div class="wrapper row-offcanvas row-offcanvas-left">
        <?php require_once(APPPATH.'views/include/sidebar.php');?>
        <aside class="right-side">
            <section class="content-header">
                <h1>Patient Preview <small>Read-only Sprint 1 context</small></h1>
                <ol class="breadcrumb">
                    <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                    <li><a href="<?php echo base_url()?>app/nursing/dashboard">Nursing Cockpit</a></li>
                    <li class="active">Patient Preview</li>
                </ol>
            </section>
            <section class="content">
                <?php if (isset($message) && $message != '') { echo $message; } ?>
                <?php $summary = isset($patient_summary) ? $patient_summary : array(); ?>
                <?php $patient = isset($summary['patient']) ? $summary['patient'] : array(); ?>
                <?php $clinical = isset($summary['summary']) ? $summary['summary'] : array(); ?>
                <div class="row">
                    <div class="col-md-4">
                        <div class="box box-primary">
                            <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-user"></i> Patient Identity</h3></div>
                            <div class="box-body">
                                <h3><?php echo htmlspecialchars(isset($patient['name']) ? $patient['name'] : 'Patient'); ?></h3>
                                <p><strong>Patient No:</strong> <?php echo htmlspecialchars(isset($patient['patient_no']) ? $patient['patient_no'] : ''); ?></p>
                                <p><strong>Encounter:</strong> <?php echo htmlspecialchars(isset($patient['encounter_id']) ? $patient['encounter_id'] : ''); ?></p>
                                <p><strong>Age/Sex:</strong> <?php echo htmlspecialchars(isset($patient['age']) ? $patient['age'] : ''); ?> / <?php echo htmlspecialchars(isset($patient['sex']) ? $patient['sex'] : ''); ?></p>
                                <p><strong>Ward/Bed:</strong> <?php echo htmlspecialchars(isset($patient['ward_name']) ? $patient['ward_name'] : ''); ?> / <?php echo htmlspecialchars(isset($patient['bed_name']) ? $patient['bed_name'] : ''); ?></p>
                                <p><strong>Admitted:</strong> <?php echo htmlspecialchars(isset($patient['admitted_at']) ? $patient['admitted_at'] : ''); ?></p>
                                <p><strong>Priority:</strong> <?php echo htmlspecialchars(isset($clinical['priority_band']) ? $clinical['priority_band'] : 'normal'); ?> / <?php echo isset($clinical['priority_score']) ? (int)$clinical['priority_score'] : 0; ?></p>
                                <?php if (isset($clinical['priority_reasons']) && count($clinical['priority_reasons']) > 0) { ?>
                                    <p><strong>Reasons:</strong> <?php echo htmlspecialchars(implode(', ', $clinical['priority_reasons'])); ?></p>
                                <?php } ?>
                                <?php if (isset($clinical['data_quality_flags']) && count($clinical['data_quality_flags']) > 0) { ?>
                                    <p><strong>Data Quality:</strong> <?php echo htmlspecialchars(implode(', ', $clinical['data_quality_flags'])); ?></p>
                                <?php } ?>
                            </div>
                            <div class="box-footer">
                                <a href="<?php echo base_url();?>app/nursing/dashboard" class="btn btn-default"><i class="fa fa-arrow-left"></i> Back</a>
                                <a href="<?php echo base_url();?>app/nursing/workspace/<?php echo urlencode(isset($patient['encounter_id']) ? $patient['encounter_id'] : ''); ?>" class="btn btn-primary"><i class="fa fa-stethoscope"></i> Workspace Placeholder</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="box box-info">
                            <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-heartbeat"></i> Latest Vitals</h3></div>
                            <div class="box-body">
                                <?php $v = isset($clinical['latest_vitals']) ? $clinical['latest_vitals'] : array(); ?>
                                <div class="row">
                                    <div class="col-sm-4"><strong>Recorded:</strong> <?php echo htmlspecialchars(isset($v['recorded_at']) ? $v['recorded_at'] : ''); ?></div>
                                    <div class="col-sm-4"><strong>Temp:</strong> <?php echo htmlspecialchars(isset($v['temperature']) ? $v['temperature'] : ''); ?></div>
                                    <div class="col-sm-4"><strong>BP:</strong> <?php echo htmlspecialchars(isset($v['bp']) ? $v['bp'] : ''); ?></div>
                                    <div class="col-sm-4"><strong>Pulse:</strong> <?php echo htmlspecialchars(isset($v['pulse']) ? $v['pulse'] : ''); ?></div>
                                    <div class="col-sm-4"><strong>Resp:</strong> <?php echo htmlspecialchars(isset($v['respiratory_rate']) ? $v['respiratory_rate'] : ''); ?></div>
                                    <div class="col-sm-4"><strong>Status:</strong> <?php echo htmlspecialchars(isset($v['status']) ? $v['status'] : ''); ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="box box-warning">
                            <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-medkit"></i> Pending Medication Summary</h3></div>
                            <div class="box-body">
                                <?php $meds = isset($clinical['pending_medications']) ? $clinical['pending_medications'] : array(); ?>
                                <?php if (count($meds) === 0) { ?><p class="text-muted">No pending medication summary.</p><?php } ?>
                                <?php foreach ($meds as $med) { ?><p><strong><?php echo htmlspecialchars($med['medication_name']); ?></strong> — <?php echo htmlspecialchars($med['dose']); ?></p><?php } ?>
                            </div>
                        </div>
                        <div class="box box-success">
                            <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-file-text"></i> Recent Notes</h3></div>
                            <div class="box-body">
                                <?php $notes = isset($clinical['recent_notes']) ? $clinical['recent_notes'] : array(); ?>
                                <?php if (count($notes) === 0) { ?><p class="text-muted">No recent nurse notes.</p><?php } ?>
                                <?php foreach ($notes as $note) { ?><p><strong><?php echo htmlspecialchars($note['recorded_at']); ?></strong><br><?php echo htmlspecialchars($note['note']); ?></p><?php } ?>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </aside>
    </div>
    <script src="<?php echo base_url();?>public/js/jquery.min.js"></script>
    <script src="<?php echo base_url();?>public/js/bootstrap.min.js" type="text/javascript"></script>
    <script src="<?php echo base_url();?>public/js/AdminLTE/app.js" type="text/javascript"></script>
</body>
</html>
