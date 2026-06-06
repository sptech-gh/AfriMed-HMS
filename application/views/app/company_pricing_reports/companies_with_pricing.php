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
                    <li class="active">Companies with Special Pricing</li>
                </ol>
            </section>
            
            <section class="content">
                <!-- Summary Cards -->
                <div class="row">
                    <div class="col-lg-3 col-xs-6">
                        <div class="small-box bg-green">
                            <div class="inner">
                                <h3><?php echo count($companies); ?></h3>
                                <p>Companies with Special Pricing</p>
                            </div>
                            <div class="icon"><i class="fa fa-building"></i></div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-xs-6">
                        <div class="small-box bg-yellow">
                            <div class="inner">
                                <h3><?php echo count(array_filter($companies, function($c) { return $c->pricing_percentage > 0; })); ?></h3>
                                <p>With Markup (+%)</p>
                            </div>
                            <div class="icon"><i class="fa fa-arrow-up"></i></div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-xs-6">
                        <div class="small-box bg-blue">
                            <div class="inner">
                                <h3><?php echo count(array_filter($companies, function($c) { return $c->pricing_percentage < 0; })); ?></h3>
                                <p>With Discount (-%)</p>
                            </div>
                            <div class="icon"><i class="fa fa-arrow-down"></i></div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-xs-6">
                        <div class="small-box bg-purple">
                            <div class="inner">
                                <h3><?php echo count(array_filter($companies, function($c) { return $c->billing_type == 'CORPORATE'; })); ?></h3>
                                <p>Corporate Accounts</p>
                            </div>
                            <div class="icon"><i class="fa fa-briefcase"></i></div>
                        </div>
                    </div>
                </div>
                
                <!-- Companies Table -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="box box-info">
                            <div class="box-header with-border">
                                <h3 class="box-title">Companies with Special Pricing Adjustments</h3>
                                <small>Only companies with non-zero pricing percentages are shown</small>
                            </div>
                            <div class="box-body table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Company Name</th>
                                            <th>Contact Person</th>
                                            <th>Phone</th>
                                            <th>Billing Type</th>
                                            <th>Pricing Adjustment</th>
                                            <th>Impact Type</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($companies)): ?>
                                            <?php foreach($companies as $c): ?>
                                            <tr>
                                                <td><strong><?php echo $c->company_name; ?></strong></td>
                                                <td><?php echo $c->contact_person; ?></td>
                                                <td><?php echo $c->contact_no_person; ?></td>
                                                <td>
                                                    <span class="label label-<?php echo ($c->billing_type == 'CORPORATE') ? 'warning' : 'info'; ?>">
                                                        <?php echo $c->billing_type; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($c->pricing_percentage > 0): ?>
                                                        <span class="label label-warning">
                                                            <i class="fa fa-arrow-up"></i> +<?php echo number_format($c->pricing_percentage, 2); ?>%
                                                        </span>
                                                    <?php elseif ($c->pricing_percentage < 0): ?>
                                                        <span class="label label-success">
                                                            <i class="fa fa-arrow-down"></i> <?php echo number_format($c->pricing_percentage, 2); ?>%
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="label label-default">0.00%</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($c->pricing_percentage > 0): ?>
                                                        <span class="text-warning">
                                                            <i class="fa fa-plus-circle"></i> Markup Applied
                                                        </span>
                                                    <?php elseif ($c->pricing_percentage < 0): ?>
                                                        <span class="text-success">
                                                            <i class="fa fa-minus-circle"></i> Discount Applied
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">
                                                            <i class="fa fa-circle"></i> Standard Pricing
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="<?php echo base_url();?>app/insurance_company/edit/<?php echo $c->in_com_id; ?>" 
                                                       class="btn btn-xs btn-primary" title="Edit Company">
                                                        <i class="fa fa-edit"></i> Edit
                                                    </a>
                                                    <a href="<?php echo base_url();?>app/company_pricing_reports/company_bills/<?php echo $c->in_com_id; ?>?from=<?php echo date('Y-m-01'); ?>&to=<?php echo date('Y-m-d'); ?>" 
                                                       class="btn btn-xs btn-info" title="View Recent Bills">
                                                        <i class="fa fa-list"></i> Bills
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="7" class="text-center text-muted">
                                                    <em>No companies with special pricing found</em>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Pricing Distribution Chart -->
                <?php if (!empty($companies)): ?>
                <?php
                $markup_count = count(array_filter($companies, function($c) { return $c->pricing_percentage > 0; }));
                $discount_count = count(array_filter($companies, function($c) { return $c->pricing_percentage < 0; }));
                $total_special = count($companies);
                ?>
                <div class="row">
                    <div class="col-md-6">
                        <div class="box box-warning">
                            <div class="box-header with-border">
                                <h3 class="box-title">Pricing Type Distribution</h3>
                            </div>
                            <div class="box-body">
                                <div class="progress-group">
                                    <span class="progress-text">Markup Companies</span>
                                    <span class="progress-number"><b><?php echo $markup_count; ?></b>/<?php echo $total_special; ?></span>
                                    <div class="progress sm">
                                        <div class="progress-bar progress-bar-yellow" style="width: <?php echo ($total_special > 0) ? ($markup_count / $total_special * 100) : 0; ?>%"></div>
                                    </div>
                                </div>
                                <div class="progress-group">
                                    <span class="progress-text">Discount Companies</span>
                                    <span class="progress-number"><b><?php echo $discount_count; ?></b>/<?php echo $total_special; ?></span>
                                    <div class="progress sm">
                                        <div class="progress-bar progress-bar-blue" style="width: <?php echo ($total_special > 0) ? ($discount_count / $total_special * 100) : 0; ?>%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="box box-success">
                            <div class="box-header with-border">
                                <h3 class="box-title">Average Pricing Adjustments</h3>
                            </div>
                            <div class="box-body">
                                <?php
                                $markup_companies = array_filter($companies, function($c) { return $c->pricing_percentage > 0; });
                                $discount_companies = array_filter($companies, function($c) { return $c->pricing_percentage < 0; });
                                $avg_markup = !empty($markup_companies) ? array_sum(array_column($markup_companies, 'pricing_percentage')) / count($markup_companies) : 0;
                                $avg_discount = !empty($discount_companies) ? array_sum(array_column($discount_companies, 'pricing_percentage')) / count($discount_companies) : 0;
                                ?>
                                <div class="info-box">
                                    <span class="info-box-icon bg-yellow"><i class="fa fa-arrow-up"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">Average Markup</span>
                                        <span class="info-box-number">+<?php echo number_format($avg_markup, 2); ?>%</span>
                                    </div>
                                </div>
                                <div class="info-box">
                                    <span class="info-box-icon bg-blue"><i class="fa fa-arrow-down"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">Average Discount</span>
                                        <span class="info-box-number"><?php echo number_format($avg_discount, 2); ?>%</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Quick Links -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="box box-default">
                            <div class="box-body">
                                <a href="<?php echo base_url();?>app/company_pricing_reports/revenue_by_company" class="btn btn-primary">
                                    <i class="fa fa-building"></i> Revenue by Company
                                </a>
                                <a href="<?php echo base_url();?>app/company_pricing_reports/pricing_impact" class="btn btn-warning">
                                    <i class="fa fa-percent"></i> Pricing Impact Analysis
                                </a>
                                <a href="<?php echo base_url();?>app/company_pricing_reports/simulate_pricing" class="btn btn-success">
                                    <i class="fa fa-calculator"></i> Pricing Simulator
                                </a>
                                <a href="<?php echo base_url();?>app/insurance_company" class="btn btn-info">
                                    <i class="fa fa-cog"></i> Manage Companies
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
