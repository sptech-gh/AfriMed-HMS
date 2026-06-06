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
                    <li class="active">Revenue by Company</li>
                </ol>
            </section>
            
            <section class="content">
                <!-- Filter Form -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="box box-primary">
                            <div class="box-header with-border">
                                <h3 class="box-title">Filter Options</h3>
                            </div>
                            <div class="box-body">
                                <form method="get" action="<?php echo base_url();?>app/company_pricing_reports/revenue_by_company">
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
                                                <label>Company</label>
                                                <select name="company_id" class="form-control">
                                                    <option value="">All Companies</option>
                                                    <?php foreach($companies as $c): ?>
                                                    <option value="<?php echo $c->in_com_id; ?>" <?php echo ($selected_company == $c->in_com_id) ? 'selected' : ''; ?>>
                                                        <?php echo $c->company_name; ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>&nbsp;</label>
                                                <button type="submit" class="btn btn-primary btn-block">
                                                    <i class="fa fa-filter"></i> Generate Report
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Summary Cards -->
                <div class="row">
                    <div class="col-lg-3 col-xs-6">
                        <div class="small-box bg-aqua">
                            <div class="inner">
                                <h3><?php echo number_format($totals['total_bills']); ?></h3>
                                <p>Total Bills</p>
                            </div>
                            <div class="icon"><i class="fa fa-file-text"></i></div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-xs-6">
                        <div class="small-box bg-green">
                            <div class="inner">
                                <h3>₵<?php echo number_format($totals['net_revenue'], 2); ?></h3>
                                <p>Net Revenue</p>
                            </div>
                            <div class="icon"><i class="fa fa-money"></i></div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-xs-6">
                        <div class="small-box bg-blue">
                            <div class="inner">
                                <h3>₵<?php echo number_format($totals['collected_amount'], 2); ?></h3>
                                <p>Collected</p>
                            </div>
                            <div class="icon"><i class="fa fa-check-circle"></i></div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-xs-6">
                        <div class="small-box bg-red">
                            <div class="inner">
                                <h3>₵<?php echo number_format($totals['outstanding_balance'], 2); ?></h3>
                                <p>Outstanding</p>
                            </div>
                            <div class="icon"><i class="fa fa-exclamation-circle"></i></div>
                        </div>
                    </div>
                </div>
                
                <!-- Report Table -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="box box-info">
                            <div class="box-header with-border">
                                <h3 class="box-title">Revenue by Company</h3>
                                <div class="box-tools pull-right">
                                    <span class="label label-primary">Period: <?php echo date('M d, Y', strtotime($from_date)); ?> - <?php echo date('M d, Y', strtotime($to_date)); ?></span>
                                </div>
                            </div>
                            <div class="box-body table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Company Name</th>
                                            <th>Type</th>
                                            <th>Pricing %</th>
                                            <th class="text-right">Bills</th>
                                            <th class="text-right">Patients</th>
                                            <th class="text-right">Gross Revenue</th>
                                            <th class="text-right">Discounts</th>
                                            <th class="text-right">Net Revenue</th>
                                            <th class="text-right">Collected</th>
                                            <th class="text-right">Outstanding</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($report)): ?>
                                            <?php foreach($report as $row): ?>
                                            <tr>
                                                <td><strong><?php echo $row->company_name; ?></strong></td>
                                                <td>
                                                    <span class="label label-<?php echo ($row->billing_type == 'CORPORATE') ? 'warning' : 'info'; ?>">
                                                        <?php echo $row->billing_type; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($row->pricing_percentage > 0): ?>
                                                        <span class="label label-warning">+<?php echo number_format($row->pricing_percentage, 2); ?>%</span>
                                                    <?php elseif ($row->pricing_percentage < 0): ?>
                                                        <span class="label label-success"><?php echo number_format($row->pricing_percentage, 2); ?>%</span>
                                                    <?php else: ?>
                                                        <span class="label label-default">0.00%</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-right"><?php echo number_format($row->total_bills); ?></td>
                                                <td class="text-right"><?php echo number_format($row->total_patients); ?></td>
                                                <td class="text-right">₵<?php echo number_format($row->gross_revenue, 2); ?></td>
                                                <td class="text-right">₵<?php echo number_format($row->total_discounts, 2); ?></td>
                                                <td class="text-right"><strong>₵<?php echo number_format($row->net_revenue, 2); ?></strong></td>
                                                <td class="text-right text-green">₵<?php echo number_format($row->collected_amount, 2); ?></td>
                                                <td class="text-right text-red">₵<?php echo number_format($row->outstanding_balance, 2); ?></td>
                                                <td>
                                                    <a href="<?php echo base_url();?>app/company_pricing_reports/company_bills/<?php echo $row->company_id; ?>?from=<?php echo $from_date; ?>&to=<?php echo $to_date; ?>" 
                                                       class="btn btn-xs btn-info" title="View Bills">
                                                        <i class="fa fa-list"></i> Bills
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="11" class="text-center text-muted">
                                                    <em>No data found for the selected period</em>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr class="bg-gray-light">
                                            <th colspan="3"><strong>TOTALS</strong></th>
                                            <th class="text-right"><?php echo number_format($totals['total_bills']); ?></th>
                                            <th class="text-right"><?php echo number_format($totals['total_patients']); ?></th>
                                            <th class="text-right">₵<?php echo number_format($totals['gross_revenue'], 2); ?></th>
                                            <th class="text-right">₵<?php echo number_format($totals['total_discounts'], 2); ?></th>
                                            <th class="text-right">₵<?php echo number_format($totals['net_revenue'], 2); ?></th>
                                            <th class="text-right">₵<?php echo number_format($totals['collected_amount'], 2); ?></th>
                                            <th class="text-right">₵<?php echo number_format($totals['outstanding_balance'], 2); ?></th>
                                            <th></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Links -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="box box-default">
                            <div class="box-body">
                                <a href="<?php echo base_url();?>app/company_pricing_reports/pricing_impact?from=<?php echo $from_date; ?>&to=<?php echo $to_date; ?>" 
                                   class="btn btn-warning">
                                    <i class="fa fa-percent"></i> View Pricing Adjustment Impact
                                </a>
                                <a href="<?php echo base_url();?>app/company_pricing_reports/companies_with_pricing" 
                                   class="btn btn-info">
                                    <i class="fa fa-building"></i> Companies with Special Pricing
                                </a>
                                <a href="<?php echo base_url();?>app/company_pricing_reports/simulate_pricing" 
                                   class="btn btn-success">
                                    <i class="fa fa-calculator"></i> Pricing Simulator
                                </a>
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
