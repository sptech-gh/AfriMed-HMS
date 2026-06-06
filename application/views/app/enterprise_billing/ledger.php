<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo isset($companyInfo->site_title) ? $companyInfo->site_title : 'HMS'; ?> — Financial Ledger</title>
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
        <h1>Financial Ledger <small>Billing & Finance</small></h1>
        <ol class="breadcrumb">
            <li><a href="<?= base_url('app/dashboard') ?>"><i class="fa fa-dashboard"></i> Home</a></li>
            <li><a href="<?= base_url('app/ebilling') ?>">Billing & Finance</a></li>
            <li class="active">Financial Ledger</li>
        </ol>
    </section>

    <section class="content">
        <!-- Date Filter -->
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-filter"></i> Filter</h3>
            </div>
            <div class="box-body">
                <form method="get" class="form-inline">
                    <div class="form-group">
                        <label>From:</label>
                        <input type="date" name="from" class="form-control" value="<?= htmlspecialchars($date_from) ?>">
                    </div>
                    <div class="form-group" style="margin-left: 15px;">
                        <label>To:</label>
                        <input type="date" name="to" class="form-control" value="<?= htmlspecialchars($date_to) ?>">
                    </div>
                    <button type="submit" class="btn btn-primary" style="margin-left: 15px;">
                        <i class="fa fa-search"></i> Filter
                    </button>
                    <a href="<?= base_url('app/ebilling/ledger') ?>" class="btn btn-default" style="margin-left: 5px;">
                        <i class="fa fa-refresh"></i> Reset
                    </a>
                </form>
            </div>
        </div>

        <!-- Ledger Entries -->
        <div class="box box-success">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-book"></i> Ledger Entries</h3>
                <div class="box-tools pull-right">
                    <span class="label label-primary"><?= count($entries) ?> entries</span>
                </div>
            </div>
            <div class="box-body table-responsive">
                <?php if (empty($entries)): ?>
                    <div class="alert alert-info">
                        <i class="fa fa-info-circle"></i> No ledger entries found for the selected period.
                    </div>
                <?php else: ?>
                    <table class="table table-bordered table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Reference</th>
                                <th>Description</th>
                                <th>Account</th>
                                <th class="text-right">Debit</th>
                                <th class="text-right">Credit</th>
                                <th class="text-right">Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $running_balance = 0;
                            foreach ($entries as $e): 
                                $debit = isset($e->debit_amount) ? floatval($e->debit_amount) : 0;
                                $credit = isset($e->credit_amount) ? floatval($e->credit_amount) : 0;
                                $running_balance += ($debit - $credit);
                            ?>
                            <tr>
                                <td><?= isset($e->entry_date) ? date('d M Y', strtotime($e->entry_date)) : '-' ?></td>
                                <td><?= isset($e->reference_no) ? htmlspecialchars($e->reference_no) : '-' ?></td>
                                <td><?= isset($e->description) ? htmlspecialchars($e->description) : '-' ?></td>
                                <td><?= isset($e->account_name) ? htmlspecialchars($e->account_name) : (isset($e->account_id) ? $e->account_id : '-') ?></td>
                                <td class="text-right"><?= $debit > 0 ? number_format($debit, 2) : '-' ?></td>
                                <td class="text-right"><?= $credit > 0 ? number_format($credit, 2) : '-' ?></td>
                                <td class="text-right <?= $running_balance >= 0 ? 'text-success' : 'text-danger' ?>">
                                    <?= number_format(abs($running_balance), 2) ?>
                                    <?= $running_balance < 0 ? ' CR' : ' DR' ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Chart of Accounts Reference -->
        <?php if (!empty($accounts)): ?>
        <div class="box box-default collapsed-box">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-list-alt"></i> Chart of Accounts</h3>
                <div class="box-tools pull-right">
                    <button type="button" class="btn btn-box-tool" data-widget="collapse">
                        <i class="fa fa-plus"></i>
                    </button>
                </div>
            </div>
            <div class="box-body table-responsive" style="display: none;">
                <table class="table table-bordered table-condensed">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Account Name</th>
                            <th>Type</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($accounts as $acc): ?>
                        <tr>
                            <td><?= isset($acc->account_code) ? htmlspecialchars($acc->account_code) : '-' ?></td>
                            <td><?= isset($acc->account_name) ? htmlspecialchars($acc->account_name) : '-' ?></td>
                            <td><?= isset($acc->account_type) ? htmlspecialchars($acc->account_type) : '-' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </section>
</div>
        </aside>
    </div>
    <script src="<?php echo base_url();?>public/js/jquery.min.js"></script>
    <script src="<?php echo base_url();?>public/js/bootstrap.min.js" type="text/javascript"></script>
    <script src="<?php echo base_url();?>public/js/AdminLTE/app.js" type="text/javascript"></script>
</body>
</html>
