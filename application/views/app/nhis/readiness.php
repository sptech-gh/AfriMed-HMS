<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Hebrew Medical Center — NHIS Claim-IT Readiness</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
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
                <h1><i class="fa fa-check-square-o"></i> NHIS Claim-IT Readiness <small>Live Integration Checklist</small></h1>
                <ol class="breadcrumb">
                    <li><a href="<?php echo base_url()?>app/nhis_claims/claimit"><i class="fa fa-cloud-upload"></i> Claim-IT</a></li>
                    <li class="active">Readiness</li>
                </ol>
            </section>

            <section class="content">
                <?php
                $c = isset($checklist) ? $checklist : [];
                $s = isset($summary) ? $summary : [];
                $mode = isset($api_mode) ? $api_mode : 'MOCK';
                $allReady = isset($c['all_ready']) && $c['all_ready'];
                ?>

                <!-- Overall Status -->
                <div class="callout callout-<?php echo $allReady ? 'success' : 'warning'; ?>">
                    <h4>
                        <i class="fa fa-<?php echo $allReady ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                        <?php echo $allReady ? 'System Ready for Live Integration' : 'Some Items Need Attention'; ?>
                    </h4>
                    <p>Current API Mode: <strong><?php echo $mode; ?></strong></p>
                </div>

                <div class="row">
                    <!-- Checklist -->
                    <div class="col-md-8">
                        <div class="box box-primary">
                            <div class="box-header with-border">
                                <h3 class="box-title"><i class="fa fa-list-ol"></i> Integration Checklist</h3>
                            </div>
                            <div class="box-body">
                                <table class="table">
                                    <tbody>
                                        <tr>
                                            <td width="50"><i class="fa fa-2x fa-<?php echo ($c['schema_created'] ?? false) ? 'check-circle text-success' : 'times-circle text-danger'; ?>"></i></td>
                                            <td><strong>Database Schema</strong><br><small class="text-muted">NHIS tables created (claims, items, diagnoses, memberships)</small></td>
                                        </tr>
                                        <tr>
                                            <td><i class="fa fa-2x fa-<?php echo ($c['icd10_seeded'] ?? false) ? 'check-circle text-success' : 'times-circle text-danger'; ?>"></i></td>
                                            <td><strong>ICD-10 Codes</strong><br><small class="text-muted">Diagnosis codes seeded and available</small></td>
                                        </tr>
                                        <tr>
                                            <td><i class="fa fa-2x fa-<?php echo ($c['tariffs_seeded'] ?? false) ? 'check-circle text-success' : 'times-circle text-danger'; ?>"></i></td>
                                            <td><strong>NHIS Tariffs</strong><br><small class="text-muted">Ghana NHIS tariff schedule configured</small></td>
                                        </tr>
                                        <tr>
                                            <td><i class="fa fa-2x fa-<?php echo ($c['api_configured'] ?? false) ? 'check-circle text-success' : 'times-circle text-danger'; ?>"></i></td>
                                            <td><strong>API Configuration</strong><br><small class="text-muted">Mock API ready, endpoints configured</small></td>
                                        </tr>
                                        <tr>
                                            <td><i class="fa fa-2x fa-<?php echo ($c['validation_engine'] ?? false) ? 'check-circle text-success' : 'times-circle text-danger'; ?>"></i></td>
                                            <td><strong>Claim Validation Engine</strong><br><small class="text-muted">Validates claims before submission</small></td>
                                        </tr>
                                        <tr>
                                            <td><i class="fa fa-2x fa-<?php echo ($c['submission_queue'] ?? false) ? 'check-circle text-success' : 'times-circle text-danger'; ?>"></i></td>
                                            <td><strong>Submission Queue</strong><br><small class="text-muted">Batch submission functionality ready</small></td>
                                        </tr>
                                        <tr>
                                            <td><i class="fa fa-2x fa-<?php echo ($c['logging_enabled'] ?? false) ? 'check-circle text-success' : 'times-circle text-danger'; ?>"></i></td>
                                            <td><strong>API Logging</strong><br><small class="text-muted">All API calls logged for audit</small></td>
                                        </tr>
                                        <tr class="<?php echo ($c['live_mode_ready'] ?? false) ? 'success' : 'warning'; ?>">
                                            <td><i class="fa fa-2x fa-<?php echo ($c['live_mode_ready'] ?? false) ? 'check-circle text-success' : 'exclamation-circle text-warning'; ?>"></i></td>
                                            <td><strong>Live Mode</strong><br><small class="text-muted"><?php echo ($c['live_mode_ready'] ?? false) ? 'Connected to Ghana NHIS Live API' : 'Currently in MOCK mode - switch to LIVE for production'; ?></small></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Summary Stats -->
                    <div class="col-md-4">
                        <div class="box box-success">
                            <div class="box-header with-border">
                                <h3 class="box-title"><i class="fa fa-bar-chart"></i> System Stats</h3>
                            </div>
                            <div class="box-body">
                                <ul class="list-group">
                                    <li class="list-group-item">
                                        <span class="badge bg-blue"><?php echo $s['total_claims'] ?? 0; ?></span>
                                        Total Claims
                                    </li>
                                    <li class="list-group-item">
                                        <span class="badge bg-yellow"><?php echo $s['draft'] ?? 0; ?></span>
                                        Draft Claims
                                    </li>
                                    <li class="list-group-item">
                                        <span class="badge bg-aqua"><?php echo $s['ready'] ?? 0; ?></span>
                                        Ready for Submission
                                    </li>
                                    <li class="list-group-item">
                                        <span class="badge bg-green"><?php echo $s['submitted'] ?? 0; ?></span>
                                        Submitted
                                    </li>
                                    <li class="list-group-item">
                                        <span class="badge bg-green"><?php echo $s['active_members'] ?? 0; ?></span>
                                        Active NHIS Members
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <div class="box box-warning">
                            <div class="box-header with-border">
                                <h3 class="box-title"><i class="fa fa-rocket"></i> Go Live</h3>
                            </div>
                            <div class="box-body">
                                <?php if($allReady && $mode !== 'LIVE'): ?>
                                <p>System is ready. Switch to LIVE mode to connect to Ghana NHIS.</p>
                                <form method="post" action="<?php echo base_url()?>app/nhis_claims/toggle_api_mode">
                                    <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                                    <input type="hidden" name="mode" value="LIVE">
                                    <button type="submit" class="btn btn-success btn-block">
                                        <i class="fa fa-power-off"></i> Switch to LIVE Mode
                                    </button>
                                </form>
                                <?php elseif($mode === 'LIVE'): ?>
                                <p class="text-success"><i class="fa fa-check"></i> System is LIVE</p>
                                <?php else: ?>
                                <p class="text-warning">Complete all checklist items before going live.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="row">
                    <div class="col-md-12">
                        <a href="<?php echo base_url()?>app/nhis_claims/claimit" class="btn btn-default">
                            <i class="fa fa-arrow-left"></i> Back to Claim-IT Dashboard
                        </a>
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
