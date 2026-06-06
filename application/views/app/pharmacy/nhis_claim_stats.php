<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>NHIS Claim Stats | Hospital Management System</title>
    <meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>
    <link href="<?php echo base_url(); ?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
</head>
<body class="skin-blue">
<?php require_once(APPPATH.'views/include/header.php');?>

<div class="wrapper row-offcanvas row-offcanvas-left">
    <?php require_once(APPPATH.'views/include/sidebar.php');?>
    
    <aside class="right-side">
    <section class="content-header">
        <h1><i class="fa fa-bar-chart"></i> NHIS Pharmacy Claim Statistics</h1>
        <ol class="breadcrumb">
            <li><a href="<?= base_url() ?>app/pharmacy"><i class="fa fa-medkit"></i> Pharmacy</a></li>
            <li><a href="<?= base_url() ?>app/pharmacy/nhis_drug_mapping">NHIS Mapping</a></li>
            <li class="active">Claim Stats</li>
        </ol>
    </section>

    <section class="content">
        <!-- Summary Cards -->
        <div class="row">
            <div class="col-md-3 col-sm-6">
                <div class="info-box bg-green">
                    <span class="info-box-icon"><i class="fa fa-link"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Mapped Drugs</span>
                        <span class="info-box-number"><?= $stats['mapped_drugs'] ?></span>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="info-box bg-yellow">
                    <span class="info-box-icon"><i class="fa fa-unlink"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Unmapped Drugs</span>
                        <span class="info-box-number"><?= $stats['unmapped_drugs'] ?></span>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="info-box bg-aqua">
                    <span class="info-box-icon"><i class="fa fa-file-text"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Claims Today</span>
                        <span class="info-box-number"><?= $stats['claims_today'] ?></span>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="info-box bg-purple">
                    <span class="info-box-icon"><i class="fa fa-money"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Monthly Claims</span>
                        <span class="info-box-number">GHS <?= number_format($stats['monthly_claim_amount'], 2) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4 col-sm-6">
                <div class="small-box bg-blue">
                    <div class="inner">
                        <h3><?= $stats['pending_claims'] ?></h3>
                        <p>Pending Claims</p>
                    </div>
                    <div class="icon"><i class="fa fa-clock-o"></i></div>
                </div>
            </div>
            <div class="col-md-4 col-sm-6">
                <div class="small-box bg-green">
                    <div class="inner">
                        <h3><?= $stats['total_tariffs'] ?></h3>
                        <p>NHIS Tariffs Available</p>
                    </div>
                    <div class="icon"><i class="fa fa-list"></i></div>
                </div>
            </div>
            <div class="col-md-4 col-sm-6">
                <div class="small-box bg-orange">
                    <div class="inner">
                        <h3><?= round(($stats['mapped_drugs'] / max(1, $stats['mapped_drugs'] + $stats['unmapped_drugs'])) * 100) ?>%</h3>
                        <p>Mapping Coverage</p>
                    </div>
                    <div class="icon"><i class="fa fa-pie-chart"></i></div>
                </div>
            </div>
        </div>

        <!-- Validation Errors -->
        <div class="box box-danger">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-exclamation-triangle"></i> Validation Errors This Month</h3>
            </div>
            <div class="box-body">
                <?php if (!empty($validation_errors)): ?>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Error Code</th>
                            <th>Count</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $error_descriptions = array(
                            'NO_MEMBERSHIP' => 'Patient has no NHIS membership record',
                            'INACTIVE_MEMBERSHIP' => 'NHIS membership is not active',
                            'EXPIRED_MEMBERSHIP' => 'NHIS membership was expired on claim date',
                            'NO_DIAGNOSIS' => 'No diagnosis attached to claim',
                            'NO_PRIMARY_DIAGNOSIS' => 'No primary diagnosis specified',
                            'INVALID_ICD10' => 'Invalid ICD-10 diagnosis code',
                            'NO_ITEMS' => 'No services/drugs attached to claim',
                            'INVALID_DRUG_CODE' => 'Invalid NHIS drug code',
                            'INVALID_QUANTITY' => 'Invalid quantity specified',
                            'EXCEEDS_MAX_SUPPLY' => 'Quantity exceeds maximum days supply',
                            'TARIFF_MISMATCH' => 'Tariff amount does not match NHIS rate',
                            'AMOUNT_CALCULATION_ERROR' => 'Amount calculation error',
                            'INVALID_AMOUNT' => 'Claim amount must be greater than zero',
                            'DRUG_NOT_MAPPED' => 'Drug is not mapped to NHIS tariff',
                            'REQUIRES_AUTHORIZATION' => 'Drug requires NHIS pre-authorization',
                            'EXCEEDS_MAX_QUANTITY' => 'Quantity exceeds maximum allowed per visit'
                        );
                        foreach ($validation_errors as $e): ?>
                        <tr>
                            <td><span class="label label-danger"><?= htmlspecialchars($e->error_code) ?></span></td>
                            <td><strong><?= $e->count ?></strong></td>
                            <td><?= isset($error_descriptions[$e->error_code]) ? $error_descriptions[$e->error_code] : 'Unknown error' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="alert alert-success">
                    <i class="fa fa-check"></i> No validation errors this month.
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="box box-info">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-bolt"></i> Quick Actions</h3>
            </div>
            <div class="box-body">
                <div class="row">
                    <div class="col-md-3">
                        <a href="<?= base_url() ?>app/pharmacy/nhis_drug_mapping" class="btn btn-block btn-success">
                            <i class="fa fa-link"></i> Map Drugs
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="<?= base_url() ?>app/pharmacy/nhis_tariffs" class="btn btn-block btn-info">
                            <i class="fa fa-list"></i> View Tariffs
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="<?= base_url() ?>app/nhis_claims" class="btn btn-block btn-primary">
                            <i class="fa fa-file-text"></i> NHIS Claims
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="<?= base_url() ?>app/pharmacy" class="btn btn-block btn-default">
                            <i class="fa fa-medkit"></i> Pharmacy
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- NHIS Compliance Checklist -->
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-check-square-o"></i> NHIS Compliance Checklist</h3>
            </div>
            <div class="box-body">
                <ul class="list-group">
                    <li class="list-group-item">
                        <?php if ($stats['mapped_drugs'] > 0): ?>
                            <i class="fa fa-check text-green"></i>
                        <?php else: ?>
                            <i class="fa fa-times text-red"></i>
                        <?php endif; ?>
                        Drug Code Mapping — <?= $stats['mapped_drugs'] ?> drugs mapped
                    </li>
                    <li class="list-group-item">
                        <?php if ($stats['total_tariffs'] > 0): ?>
                            <i class="fa fa-check text-green"></i>
                        <?php else: ?>
                            <i class="fa fa-times text-red"></i>
                        <?php endif; ?>
                        NHIS Tariffs Loaded — <?= $stats['total_tariffs'] ?> tariffs available
                    </li>
                    <li class="list-group-item">
                        <i class="fa fa-check text-green"></i>
                        Auto Claim Item Creation — Enabled
                    </li>
                    <li class="list-group-item">
                        <i class="fa fa-check text-green"></i>
                        Membership Verification — Active
                    </li>
                    <li class="list-group-item">
                        <i class="fa fa-check text-green"></i>
                        Claim Validation Layer — Active
                    </li>
                </ul>
            </div>
        </div>
    </section>
</div>
    </aside>
</div>

<script src="<?php echo base_url(); ?>public/js/jquery.min.js"></script>
<script src="<?php echo base_url(); ?>public/js/bootstrap.min.js"></script>
<script src="<?php echo base_url(); ?>public/js/AdminLTE/app.js"></script>
</body>
</html>
