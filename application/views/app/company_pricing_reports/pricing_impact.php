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
                    <li class="active">Pricing Impact</li>
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
                                <form method="get" action="<?php echo base_url();?>app/company_pricing_reports/pricing_impact">
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
                    <div class="col-lg-4 col-xs-12">
                        <div class="small-box bg-blue">
                            <div class="inner">
                                <h3>₵<?php echo number_format($impact_summary->total_base_revenue ?? 0, 2); ?></h3>
                                <p>Base Revenue (Without Adjustments)</p>
                            </div>
                            <div class="icon"><i class="fa fa-calculator"></i></div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-xs-12">
                        <div class="small-box bg-green">
                        <div class="inner">
                                <h3>₵<?php echo number_format($impact_summary->total_adjusted_revenue ?? 0, 2); ?></h3>
                                <p>Adjusted Revenue (With Pricing)</p>
                            </div>
                            <div class="icon"><i class="fa fa-money"></i></div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-xs-12">
                        <div class="small-box <?php echo (($impact_summary->total_adjustment ?? 0) >= 0) ? 'bg-yellow' : 'bg-red'; ?>">
                            <div class="inner">
                                <h3>
                                    <?php echo (($impact_summary->total_adjustment ?? 0) >= 0) ? '+' : ''; ?>
                                    ₵<?php echo number_format($impact_summary->total_adjustment ?? 0, 2); ?>
                                </h3>
                                <p>Net Pricing Impact</p>
                            </div>
                            <div class="icon">
                                <i class="fa fa-<?php echo (($impact_summary->total_adjustment ?? 0) >= 0) ? 'arrow-up' : 'arrow-down'; ?>"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Impact Stats -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="info-box">
                            <span class="info-box-icon bg-aqua"><i class="fa fa-building"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Companies with Adjustments</span>
                                <span class="info-box-number"><?php echo number_format($impact_summary->companies_with_adjustments ?? 0); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-box">
                            <span class="info-box-icon bg-purple"><i class="fa fa-list"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Total Adjusted Items</span>
                                <span class="info-box-number"><?php echo number_format($impact_summary->total_adjusted_items ?? 0); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Detailed Impact Table -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="box box-warning">
                            <div class="box-header with-border">
                                <h3 class="box-title">Detailed Adjustment Impact by Service Type</h3>
                                <div class="box-tools pull-right">
                                    <span class="label label-warning">Period: <?php echo date('M d, Y', strtotime($from_date)); ?> - <?php echo date('M d, Y', strtotime($to_date)); ?></span>
                                </div>
                            </div>
                            <div class="box-body table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Company</th>
                                            <th>Pricing %</th>
                                            <th>Service Type</th>
                                            <th class="text-right">Items</th>
                                            <th class="text-right">Base Revenue</th>
                                            <th class="text-right">Adjusted Revenue</th>
                                            <th class="text-right">Adjustment Amount</th>
                                            <th>Impact</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($impact_details)): ?>
                                            <?php 
                                            $current_company = '';
                                            $company_total = 0;
                                            ?>
                                            <?php foreach($impact_details as $row): 
                                                $impact_pct = ($row->base_revenue > 0) ? (($row->adjustment_amount / $row->base_revenue) * 100) : 0;
                                            ?>
                                            <tr>
                                                <td><strong><?php echo $row->company_name; ?></strong></td>
                                                <td>
                                                    <?php if ($row->pricing_percentage > 0): ?>
                                                        <span class="label label-warning">+<?php echo number_format($row->pricing_percentage, 2); ?>%</span>
                                                    <?php elseif ($row->pricing_percentage < 0): ?>
                                                        <span class="label label-success"><?php echo number_format($row->pricing_percentage, 2); ?>%</span>
                                                    <?php else: ?>
                                                        <span class="label label-default">0.00%</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo $row->service_type; ?></td>
                                                <td class="text-right"><?php echo number_format($row->item_count); ?></td>
                                                <td class="text-right">₵<?php echo number_format($row->base_revenue, 2); ?></td>
                                                <td class="text-right">₵<?php echo number_format($row->adjusted_revenue, 2); ?></td>
                                                <td class="text-right <?php echo ($row->adjustment_amount >= 0) ? 'text-green' : 'text-red'; ?>">
                                                    <?php echo ($row->adjustment_amount >= 0) ? '+' : ''; ?>
                                                    ₵<?php echo number_format($row->adjustment_amount, 2); ?>
                                                </td>
                                                <td>
                                                    <div class="progress" style="margin-bottom: 0;">
                                                        <?php 
                                                        $progress_class = ($row->adjustment_amount >= 0) ? 'progress-bar-warning' : 'progress-bar-success';
                                                        $progress_width = min(abs($impact_pct), 100);
                                                        ?>
                                                        <div class="progress-bar <?php echo $progress_class; ?>" 
                                                             role="progressbar" 
                                                             style="width: <?php echo $progress_width; ?>%"
                                                             aria-valuenow="<?php echo $progress_width; ?>" 
                                                             aria-valuemin="0" 
                                                             aria-valuemax="100">
                                                            <?php echo number_format($impact_pct, 1); ?>%
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="8" class="text-center text-muted">
                                                    <em>No pricing adjustments found for the selected period</em>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Explanation -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="callout callout-info">
                            <h4><i class="fa fa-info-circle"></i> Understanding this Report</h4>
                            <p>
                                This report shows the financial impact of company cover pricing adjustments. 
                                <strong>Base Revenue</strong> is what the revenue would be at standard prices. 
                                <strong>Adjusted Revenue</strong> includes the markup/discount applied for each company. 
                                The <strong>Adjustment Amount</strong> shows the net difference (positive = additional revenue from markups, negative = revenue reduction from discounts).
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Links -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="box box-default">
                            <div class="box-body">
                                <a href="<?php echo base_url();?>app/company_pricing_reports/revenue_by_company?from=<?php echo $from_date; ?>&to=<?php echo $to_date; ?>" 
                                   class="btn btn-primary">
                                    <i class="fa fa-building"></i> Revenue by Company
                                </a>
                                <a href="<?php echo base_url();?>app/company_pricing_reports/companies_with_pricing" 
                                   class="btn btn-info">
                                    <i class="fa fa-list"></i> Companies with Special Pricing
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
