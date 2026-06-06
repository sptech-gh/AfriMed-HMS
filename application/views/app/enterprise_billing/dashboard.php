<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo isset($companyInfo->site_title) ? $companyInfo->site_title : 'HMS'; ?> — Billing & Finance</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
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
<div class="content-wrapper">
    <section class="content-header">
        <h1>Billing & Finance <small>Single Source of Truth</small></h1>
        <ol class="breadcrumb">
            <li><a href="<?= base_url('app/dashboard') ?>"><i class="fa fa-dashboard"></i> Home</a></li>
            <li class="active">Billing & Finance</li>
        </ol>
    </section>

    <section class="content">
        <!-- Quick Search -->
        <div class="row">
            <div class="col-md-12">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-search"></i> Quick Patient Search</h3>
                    </div>
                    <div class="box-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="input-group">
                                    <input type="text" id="patient_search" class="form-control" 
                                           placeholder="Search by Patient No, Name, or Phone...">
                                    <span class="input-group-btn">
                                        <button class="btn btn-primary" type="button" id="btn_search">
                                            <i class="fa fa-search"></i> Search
                                        </button>
                                    </span>
                                </div>
                            </div>
                            <div class="col-md-6 text-right">
                                <a href="<?= base_url('app/ebilling/blocked_services') ?>" class="btn btn-warning">
                                    <i class="fa fa-lock"></i> Blocked Services 
                                    <span class="badge"><?= $blocked_services ?></span>
                                </a>
                                <a href="<?= base_url('app/ebilling/reconciliation_dashboard') ?>" class="btn btn-info">
                                    <i class="fa fa-balance-scale"></i> Reconciliation
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row">
            <div class="col-lg-3 col-xs-6">
                <div class="small-box bg-aqua">
                    <div class="inner">
                        <h3><?= $today_invoices ?></h3>
                        <p>Today's Invoices</p>
                    </div>
                    <div class="icon"><i class="fa fa-file-text-o"></i></div>
                    <a href="<?= base_url('app/ebilling/daily_report') ?>" class="small-box-footer">
                        View Report <i class="fa fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>
            
            <div class="col-lg-3 col-xs-6">
                <div class="small-box bg-green">
                    <div class="inner">
                        <h3>GHS <?= number_format($today_collections, 2) ?></h3>
                        <p>Today's Collections</p>
                    </div>
                    <div class="icon"><i class="fa fa-money"></i></div>
                    <a href="<?= base_url('app/ebilling/daily_report') ?>" class="small-box-footer">
                        View Details <i class="fa fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>
            
            <div class="col-lg-3 col-xs-6">
                <div class="small-box bg-yellow">
                    <div class="inner">
                        <h3><?= $pending_payments ?></h3>
                        <p>Pending Payments</p>
                    </div>
                    <div class="icon"><i class="fa fa-clock-o"></i></div>
                    <a href="<?= base_url('app/ebilling/outstanding_report') ?>" class="small-box-footer">
                        View All <i class="fa fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>
            
            <div class="col-lg-3 col-xs-6">
                <div class="small-box bg-red">
                    <div class="inner">
                        <h3><?= $blocked_services ?></h3>
                        <p>Blocked Services</p>
                    </div>
                    <div class="icon"><i class="fa fa-ban"></i></div>
                    <a href="<?= base_url('app/ebilling/blocked_services') ?>" class="small-box-footer">
                        Manage <i class="fa fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Discrepancies Alert -->
        <?php if ($discrepancies['total'] > 0): ?>
        <div class="row">
            <div class="col-md-12">
                <div class="alert alert-<?= $discrepancies['critical'] > 0 ? 'danger' : ($discrepancies['high'] > 0 ? 'warning' : 'info') ?>">
                    <h4><i class="icon fa fa-warning"></i> Billing Discrepancies Detected</h4>
                    <p>
                        <strong><?= $discrepancies['total'] ?></strong> discrepancies found: 
                        <?php if ($discrepancies['critical'] > 0): ?>
                            <span class="label label-danger"><?= $discrepancies['critical'] ?> Critical</span>
                        <?php endif; ?>
                        <?php if ($discrepancies['high'] > 0): ?>
                            <span class="label label-warning"><?= $discrepancies['high'] ?> High</span>
                        <?php endif; ?>
                        <?php if ($discrepancies['medium'] > 0): ?>
                            <span class="label label-info"><?= $discrepancies['medium'] ?> Medium</span>
                        <?php endif; ?>
                        <a href="<?= base_url('app/ebilling/reconciliation_dashboard') ?>" class="btn btn-sm btn-default pull-right">
                            View & Fix
                        </a>
                    </p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="row">
            <!-- Recent Transactions -->
            <div class="col-md-8">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-list"></i> Recent Transactions</h3>
                    </div>
                    <div class="box-body table-responsive no-padding">
                        <table class="table table-hover table-striped">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Patient</th>
                                    <th>Type</th>
                                    <th>Description</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_transactions as $txn): ?>
                                <tr>
                                    <td><?= date('H:i', strtotime($txn->created_at)) ?></td>
                                    <td>
                                        <a href="<?= base_url('app/ebilling/patient/' . $txn->patient_no) ?>">
                                            <?= $txn->firstname ?> <?= $txn->lastname ?>
                                        </a>
                                    </td>
                                    <td><span class="label label-default"><?= $txn->charge_type ?></span></td>
                                    <td><?= $txn->charge_name ?></td>
                                    <td>GHS <?= number_format($txn->patient_amount, 2) ?></td>
                                    <td>
                                        <?php
                                        $status_class = [
                                            'PENDING' => 'warning',
                                            'INVOICED' => 'info',
                                            'PAID' => 'success',
                                            'PARTIAL' => 'primary',
                                            'CANCELLED' => 'danger',
                                            'WAIVED' => 'default'
                                        ];
                                        $class = $status_class[$txn->billing_status] ?? 'default';
                                        ?>
                                        <span class="label label-<?= $class ?>"><?= $txn->billing_status ?></span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="col-md-4">
                <div class="box box-success">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-bolt"></i> Quick Actions</h3>
                    </div>
                    <div class="box-body">
                        <a href="<?= base_url('app/ebilling/collect_payment') ?>" class="btn btn-success btn-block btn-lg">
                            <i class="fa fa-money"></i> Collect Payment
                        </a>
                        <hr>
                        <a href="<?= base_url('app/ebilling/daily_report') ?>" class="btn btn-info btn-block">
                            <i class="fa fa-calendar"></i> Daily Report
                        </a>
                        <a href="<?= base_url('app/ebilling/department_report') ?>" class="btn btn-info btn-block">
                            <i class="fa fa-building"></i> Department Revenue
                        </a>
                        <a href="<?= base_url('app/ebilling/outstanding_report') ?>" class="btn btn-warning btn-block">
                            <i class="fa fa-exclamation-triangle"></i> Outstanding Balances
                        </a>
                        <a href="<?= base_url('app/ebilling/refunds') ?>" class="btn btn-danger btn-block">
                            <i class="fa fa-undo"></i> Refund Requests
                        </a>
                    </div>
                </div>

                <!-- Payment Methods Summary -->
                <div class="box box-info">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-pie-chart"></i> Today by Payment Method</h3>
                    </div>
                    <div class="box-body">
                        <div class="progress-group">
                            <span class="progress-text">Cash</span>
                            <span class="progress-number" id="cash_total">Loading...</span>
                        </div>
                        <div class="progress-group">
                            <span class="progress-text">Mobile Money</span>
                            <span class="progress-number" id="momo_total">Loading...</span>
                        </div>
                        <div class="progress-group">
                            <span class="progress-text">Card</span>
                            <span class="progress-number" id="card_total">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<script>
$(document).ready(function() {
    // Patient search
    $('#patient_search').on('keypress', function(e) {
        if (e.which === 13) {
            searchPatient();
        }
    });
    
    $('#btn_search').click(function() {
        searchPatient();
    });
    
    function searchPatient() {
        var term = $('#patient_search').val().trim();
        if (term.length < 2) {
            alert('Please enter at least 2 characters');
            return;
        }
        
        $.get('<?= base_url('app/ebilling/search_patient') ?>', {term: term}, function(data) {
            var results = JSON.parse(data);
            if (results.length === 1) {
                window.location.href = '<?= base_url('app/ebilling/patient/') ?>' + results[0].patient_no;
            } else if (results.length > 1) {
                // Show selection modal
                var html = '<div class="list-group">';
                results.forEach(function(p) {
                    html += '<a href="<?= base_url('app/ebilling/patient/') ?>' + p.patient_no + '" class="list-group-item">';
                    html += '<strong>' + p.patient_no + '</strong> - ' + p.name;
                    html += '<span class="pull-right badge">' + p.type + '</span>';
                    html += '</a>';
                });
                html += '</div>';
                
                bootbox.dialog({
                    title: 'Select Patient',
                    message: html,
                    size: 'small'
                });
            } else {
                alert('No patients found');
            }
        });
    }
});
</script>
        </aside>
    </div>
    <script src="<?php echo base_url();?>public/js/jquery.min.js"></script>
    <script src="<?php echo base_url();?>public/js/bootstrap.min.js" type="text/javascript"></script>
    <script src="<?php echo base_url();?>public/js/AdminLTE/app.js" type="text/javascript"></script>
</body>
</html>
