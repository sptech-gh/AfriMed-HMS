<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo $page_title; ?> - Hebrew Medical Center</title>
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
                <h1><?php echo $page_title; ?></h1>
                <ol class="breadcrumb">
                    <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                    <li><a href="#">Reports</a></li>
                    <li><a href="<?php echo base_url();?>app/company_pricing_reports/revenue_by_company">Revenue by Company</a></li>
                    <li class="active">Company Bills</li>
                </ol>
            </section>
            
            <section class="content">
                <!-- Company Info -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="box box-primary">
                            <div class="box-header with-border">
                                <h3 class="box-title">Company Information</h3>
                            </div>
                            <div class="box-body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <strong>Company Name:</strong><br>
                                        <?php echo $company->company_name; ?>
                                    </div>
                                    <div class="col-md-3">
                                        <strong>Billing Type:</strong><br>
                                        <span class="label label-<?php echo ($company->billing_type == 'CORPORATE') ? 'warning' : 'info'; ?>">
                                            <?php echo $company->billing_type; ?>
                                        </span>
                                    </div>
                                    <div class="col-md-3">
                                        <strong>Pricing Adjustment:</strong><br>
                                        <?php if ($company->pricing_percentage > 0): ?>
                                            <span class="label label-warning">+<?php echo number_format($company->pricing_percentage, 2); ?>% Markup</span>
                                        <?php elseif ($company->pricing_percentage < 0): ?>
                                            <span class="label label-success"><?php echo number_format($company->pricing_percentage, 2); ?>% Discount</span>
                                        <?php else: ?>
                                            <span class="label label-default">0.00% (Standard Pricing)</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-3">
                                        <strong>Period:</strong><br>
                                        <?php echo date('M d, Y', strtotime($from_date)); ?> - <?php echo date('M d, Y', strtotime($to_date)); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filter Form -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="box box-default">
                            <div class="box-body">
                                <form method="get" action="<?php echo base_url();?>app/company_pricing_reports/company_bills/<?php echo $company_id; ?>">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>From Date</label>
                                                <input type="date" name="from" class="form-control" value="<?php echo $from_date; ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>To Date</label>
                                                <input type="date" name="to" class="form-control" value="<?php echo $to_date; ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>&nbsp;</label>
                                                <button type="submit" class="btn btn-primary btn-block">
                                                    <i class="fa fa-filter"></i> Update Period
                                                </button>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>&nbsp;</label>
                                                <a href="<?php echo base_url();?>app/company_pricing_reports/revenue_by_company" class="btn btn-default btn-block">
                                                    <i class="fa fa-arrow-left"></i> Back to Reports
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Bills Table -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="box box-info">
                            <div class="box-header with-border">
                                <h3 class="box-title">Patient Bills</h3>
                                <div class="box-tools pull-right">
                                    <span class="label label-info"><?php echo count($bills); ?> bills found</span>
                                </div>
                            </div>
                            <div class="box-body table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Bill No.</th>
                                            <th>Patient Name</th>
                                            <th>Patient No.</th>
                                            <th>Bill Date</th>
                                            <th class="text-right">Items</th>
                                            <th class="text-right">Total Amount</th>
                                            <th class="text-right">Net Amount</th>
                                            <th class="text-right">Collected</th>
                                            <th class="text-right">Balance</th>
                                            <th class="text-right">Adjustment</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($bills)): ?>
                                            <?php foreach($bills as $bill): ?>
                                            <tr>
                                                <td><strong><?php echo $bill->bill_no; ?></strong></td>
                                                <td><?php echo $bill->patient_name; ?></td>
                                                <td><?php echo $bill->patient_no; ?></td>
                                                <td><?php echo date('M d, Y', strtotime($bill->bill_date)); ?></td>
                                                <td class="text-right"><?php echo number_format($bill->item_count); ?></td>
                                                <td class="text-right">₵<?php echo number_format($bill->total_amount, 2); ?></td>
                                                <td class="text-right"><strong>₵<?php echo number_format($bill->net_amount, 2); ?></strong></td>
                                                <td class="text-right text-green">₵<?php echo number_format($bill->paid_amount, 2); ?></td>
                                                <td class="text-right text-red">₵<?php echo number_format($bill->balance_due, 2); ?></td>
                                                <td class="text-right <?php echo ($bill->total_adjustment >= 0) ? 'text-green' : 'text-red'; ?>">
                                                    <?php echo ($bill->total_adjustment >= 0) ? '+' : ''; ?>
                                                    ₵<?php echo number_format($bill->total_adjustment, 2); ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $status_class = '';
                                                    switch($bill->payment_status) {
                                                        case 'PAID':
                                                            $status_class = 'success';
                                                            break;
                                                        case 'PARTIAL':
                                                            $status_class = 'warning';
                                                            break;
                                                        case 'PENDING':
                                                            $status_class = 'danger';
                                                            break;
                                                        default:
                                                            $status_class = 'default';
                                                    }
                                                    ?>
                                                    <span class="label label-<?php echo $status_class; ?>">
                                                        <?php echo $bill->payment_status; ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="11" class="text-center text-muted">
                                                    <em>No bills found for the selected period</em>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Summary Statistics -->
                <?php if (!empty($bills)): ?>
                <?php
                $totals = [
                    'bills' => count($bills),
                    'items' => array_sum(array_column($bills, 'item_count')),
                    'total_amount' => array_sum(array_column($bills, 'total_amount')),
                    'net_amount' => array_sum(array_column($bills, 'net_amount')),
                    'collected' => array_sum(array_column($bills, 'paid_amount')),
                    'balance' => array_sum(array_column($bills, 'balance_due')),
                    'adjustments' => array_sum(array_column($bills, 'total_adjustment'))
                ];
                ?>
                <div class="row">
                    <div class="col-lg-3 col-xs-6">
                        <div class="small-box bg-aqua">
                            <div class="inner">
                                <h3><?php echo number_format($totals['bills']); ?></h3>
                                <p>Total Bills</p>
                            </div>
                            <div class="icon"><i class="fa fa-file-text"></i></div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-xs-6">
                        <div class="small-box bg-green">
                            <div class="inner">
                                <h3>₵<?php echo number_format($totals['net_amount'], 2); ?></h3>
                                <p>Net Revenue</p>
                            </div>
                            <div class="icon"><i class="fa fa-money"></i></div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-xs-6">
                        <div class="small-box bg-blue">
                            <div class="inner">
                                <h3>₵<?php echo number_format($totals['collected'], 2); ?></h3>
                                <p>Collected</p>
                            </div>
                            <div class="icon"><i class="fa fa-check-circle"></i></div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-xs-6">
                        <div class="small-box <?php echo ($totals['adjustments'] >= 0) ? 'bg-yellow' : 'bg-red'; ?>">
                            <div class="inner">
                                <h3>
                                    <?php echo ($totals['adjustments'] >= 0) ? '+' : ''; ?>
                                    ₵<?php echo number_format($totals['adjustments'], 2); ?>
                                </h3>
                                <p>Total Adjustments</p>
                            </div>
                            <div class="icon">
                                <i class="fa fa-<?php echo ($totals['adjustments'] >= 0) ? 'arrow-up' : 'arrow-down'; ?>"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </section>
        </aside>
    </div>
    
    <script src="<?php echo base_url();?>public/js/jquery.min.js"></script>
    <script src="<?php echo base_url();?>public/js/bootstrap.min.js"></script>
    <script src="<?php echo base_url();?>public/js/AdminLTE/app.js"></script>
</body>
</html>
