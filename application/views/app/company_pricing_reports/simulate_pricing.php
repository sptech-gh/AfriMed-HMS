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
                    <li class="active">Pricing Simulator</li>
                </ol>
            </section>
            
            <section class="content">
                <!-- Simulator Form -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="box box-success">
                            <div class="box-header with-border">
                                <h3 class="box-title">Pricing Change Simulator</h3>
                                <small>Forecast revenue impact of changing company pricing percentages</small>
                            </div>
                            <div class="box-body">
                                <form method="post" action="<?php echo base_url();?>app/company_pricing_reports/simulate_pricing">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Company *</label>
                                                <select name="company_id" class="form-control" required>
                                                    <option value="">Select Company</option>
                                                    <?php foreach($companies as $c): ?>
                                                    <option value="<?php echo $c->in_com_id; ?>" <?php echo ($selected_company == $c->in_com_id) ? 'selected' : ''; ?>>
                                                        <?php echo $c->company_name; ?>
                                                        <?php if (isset($c->pricing_percentage) && $c->pricing_percentage != 0): ?>
                                                            (<?php echo ($c->pricing_percentage > 0) ? '+' : ''; ?><?php echo number_format($c->pricing_percentage, 2); ?>%)
                                                        <?php endif; ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>New Pricing Percentage *</label>
                                                <div class="input-group">
                                                    <input type="number" name="new_percentage" class="form-control" 
                                                           value="<?php echo $new_percentage; ?>" 
                                                           step="0.01" required>
                                                    <span class="input-group-addon">%</span>
                                                </div>
                                                <small class="text-muted">Use positive for markup, negative for discount</small>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label>From Date</label>
                                                <input type="date" name="from" class="form-control" value="<?php echo $from_date; ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label>To Date</label>
                                                <input type="date" name="to" class="form-control" value="<?php echo $to_date; ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label>&nbsp;</label>
                                                <button type="submit" name="simulate" class="btn btn-success btn-block">
                                                    <i class="fa fa-calculator"></i> Simulate
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Simulation Results -->
                <?php if (isset($simulation_result) && $simulation_result): ?>
                <div class="row">
                    <div class="col-md-12">
                        <div class="box box-info">
                            <div class="box-header with-border">
                                <h3 class="box-title">Simulation Results</h3>
                                <small>
                                    For <?php echo $company_info->company_name; ?> 
                                    (<?php echo date('M d, Y', strtotime($from_date)); ?> - <?php echo date('M d, Y', strtotime($to_date)); ?>)
                                </small>
                            </div>
                            <div class="box-body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="info-box bg-blue">
                                            <span class="info-box-icon"><i class="fa fa-calculator"></i></span>
                                            <div class="info-box-content">
                                                <span class="info-box-text">Base Revenue</span>
                                                <span class="info-box-number">₵<?php echo number_format($simulation_result->base_revenue, 2); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="info-box bg-green">
                                            <span class="info-box-icon"><i class="fa fa-money"></i></span>
                                            <div class="info-box-content">
                                                <span class="info-box-text">Projected Revenue</span>
                                                <span class="info-box-number">₵<?php echo number_format($simulation_result->projected_revenue, 2); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="info-box <?php echo ($simulation_result->projected_difference >= 0) ? 'bg-yellow' : 'bg-red'; ?>">
                                            <span class="info-box-icon">
                                                <i class="fa fa-<?php echo ($simulation_result->projected_difference >= 0) ? 'arrow-up' : 'arrow-down'; ?>"></i>
                                            </span>
                                            <div class="info-box-content">
                                                <span class="info-box-text">Revenue Difference</span>
                                                <span class="info-box-number">
                                                    <?php echo ($simulation_result->projected_difference >= 0) ? '+' : ''; ?>
                                                    ₵<?php echo number_format($simulation_result->projected_difference, 2); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="info-box bg-purple">
                                            <span class="info-box-icon"><i class="fa fa-list"></i></span>
                                            <div class="info-box-content">
                                                <span class="info-box-text">Affected Items</span>
                                                <span class="info-box-number"><?php echo number_format($simulation_result->affected_items); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Impact Analysis -->
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="callout callout-<?php echo ($simulation_result->projected_difference >= 0) ? 'success' : 'warning'; ?>">
                                            <h4><i class="fa fa-info-circle"></i> Impact Analysis</h4>
                                            <p>
                                                Applying a <strong><?php echo ($new_percentage >= 0) ? '+' : ''; ?><?php echo number_format($new_percentage, 2); ?>%</strong> pricing adjustment 
                                                to <?php echo $company_info->company_name; ?> would:
                                            </p>
                                            <ul>
                                                <li>
                                                    <?php if ($simulation_result->projected_difference >= 0): ?>
                                                        <strong>Increase revenue</strong> by ₵<?php echo number_format($simulation_result->projected_difference, 2); ?>
                                                        (<?php echo number_format(($simulation_result->base_revenue > 0) ? ($simulation_result->projected_difference / $simulation_result->base_revenue * 100) : 0, 2); ?>%)
                                                    <?php else: ?>
                                                        <strong>Decrease revenue</strong> by ₵<?php echo number_format(abs($simulation_result->projected_difference), 2); ?>
                                                        (<?php echo number_format(($simulation_result->base_revenue > 0) ? (abs($simulation_result->projected_difference) / $simulation_result->base_revenue * 100) : 0, 2); ?>%)
                                                    <?php endif; ?>
                                                </li>
                                                <li>Affect <strong><?php echo number_format($simulation_result->affected_items); ?></strong> billing items</li>
                                                <li>Change average price per item by 
                                                    <?php echo ($simulation_result->affected_items > 0) ? 
                                                        number_format($simulation_result->projected_difference / $simulation_result->affected_items, 2) : 0; ?>%
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Current Pricing Status -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="box box-default">
                            <div class="box-header with-border">
                                <h3 class="box-title">Current Company Pricing Status</h3>
                            </div>
                            <div class="box-body table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Company Name</th>
                                            <th>Current Pricing %</th>
                                            <th>Billing Type</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($companies as $c): ?>
                                        <tr>
                                            <td><strong><?php echo $c->company_name; ?></strong></td>
                                            <td>
                                                <?php if (isset($c->pricing_percentage) && $c->pricing_percentage != 0): ?>
                                                    <span class="label <?php echo ($c->pricing_percentage > 0) ? 'label-warning' : 'label-success'; ?>">
                                                        <?php echo ($c->pricing_percentage > 0) ? '+' : ''; ?><?php echo number_format($c->pricing_percentage, 2); ?>%
                                                    </span>
                                                <?php else: ?>
                                                    <span class="label label-default">0.00%</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="label label-<?php echo ($c->billing_type == 'CORPORATE') ? 'warning' : 'info'; ?>">
                                                    <?php echo $c->billing_type; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if (isset($c->pricing_percentage) && $c->pricing_percentage != 0): ?>
                                                    <span class="text-success"><i class="fa fa-check-circle"></i> Special Pricing Active</span>
                                                <?php else: ?>
                                                    <span class="text-muted"><i class="fa fa-circle"></i> Standard Pricing</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
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
                                <a href="<?php echo base_url();?>app/company_pricing_reports/revenue_by_company" class="btn btn-primary">
                                    <i class="fa fa-building"></i> Revenue by Company
                                </a>
                                <a href="<?php echo base_url();?>app/company_pricing_reports/pricing_impact" class="btn btn-warning">
                                    <i class="fa fa-percent"></i> Pricing Impact Analysis
                                </a>
                                <a href="<?php echo base_url();?>app/company_pricing_reports/companies_with_pricing" class="btn btn-info">
                                    <i class="fa fa-list"></i> Companies with Special Pricing
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
