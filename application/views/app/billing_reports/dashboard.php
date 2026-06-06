<?php $this->load->view("admin/include/header"); ?>
<?php $this->load->view("admin/include/sidebar"); ?>

<div class="content-wrapper">
    <section class="content-header">
        <h1>
            <i class="fa fa-bar-chart"></i> Billing Reports Dashboard
            <small>Financial Analytics & Reports</small>
        </h1>
        <ol class="breadcrumb">
            <li><a href="<?php echo base_url(); ?>app/dashboard"><i class="fa fa-dashboard"></i> Dashboard</a></li>
            <li class="active">Billing Reports</li>
        </ol>
    </section>

    <section class="content">
        <!-- Today's Summary -->
        <div class="row">
            <div class="col-md-3 col-sm-6">
                <div class="info-box bg-aqua">
                    <span class="info-box-icon"><i class="fa fa-money"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Today's Billed</span>
                        <span class="info-box-number">GH₵ <?php echo number_format($daily_revenue['total_billed'], 2); ?></span>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="info-box bg-green">
                    <span class="info-box-icon"><i class="fa fa-check-circle"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Today's Collected</span>
                        <span class="info-box-number">GH₵ <?php echo number_format($daily_revenue['total_collected'], 2); ?></span>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="info-box bg-yellow">
                    <span class="info-box-icon"><i class="fa fa-clock-o"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Outstanding</span>
                        <span class="info-box-number">GH₵ <?php echo number_format($outstanding['total_outstanding'], 2); ?></span>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="info-box bg-red">
                    <span class="info-box-icon"><i class="fa fa-file-text-o"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Pending Bills</span>
                        <span class="info-box-number"><?php echo number_format($outstanding['total_bills']); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Report Links -->
        <div class="row">
            <div class="col-md-6">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-list"></i> Revenue Reports</h3>
                    </div>
                    <div class="box-body">
                        <div class="list-group">
                            <a href="<?php echo base_url(); ?>app/billing_reports/daily_revenue" class="list-group-item">
                                <i class="fa fa-calendar-o text-aqua"></i> <strong>Daily Revenue</strong>
                                <span class="pull-right text-muted">Today's collections breakdown</span>
                            </a>
                            <a href="<?php echo base_url(); ?>app/billing_reports/revenue_summary" class="list-group-item">
                                <i class="fa fa-line-chart text-green"></i> <strong>Revenue Summary</strong>
                                <span class="pull-right text-muted">Monthly/Custom date range</span>
                            </a>
                            <a href="<?php echo base_url(); ?>app/billing_reports/department_revenue" class="list-group-item">
                                <i class="fa fa-building text-purple"></i> <strong>Department Revenue</strong>
                                <span class="pull-right text-muted">Revenue by department</span>
                            </a>
                            <a href="<?php echo base_url(); ?>app/billing_reports/outstanding" class="list-group-item">
                                <i class="fa fa-exclamation-triangle text-yellow"></i> <strong>Outstanding Bills</strong>
                                <span class="pull-right text-muted">Unpaid bills aging report</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="box box-success">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-users"></i> Performance & Claims</h3>
                    </div>
                    <div class="box-body">
                        <div class="list-group">
                            <a href="<?php echo base_url(); ?>app/billing_reports/cashier_performance" class="list-group-item">
                                <i class="fa fa-user text-blue"></i> <strong>Cashier Performance</strong>
                                <span class="pull-right text-muted">Collections by cashier</span>
                            </a>
                            <a href="<?php echo base_url(); ?>app/billing_reports/nhis_claims" class="list-group-item">
                                <i class="fa fa-medkit text-green"></i> <strong>NHIS Claims Report</strong>
                                <span class="pull-right text-muted">Insurance claims status</span>
                            </a>
                            <a href="<?php echo base_url(); ?>app/billing_reports/export_nhis_claims?from=<?php echo date('Y-m-01'); ?>&to=<?php echo date('Y-m-d'); ?>" class="list-group-item">
                                <i class="fa fa-download text-orange"></i> <strong>Export NHIS Claims</strong>
                                <span class="pull-right text-muted">Download CSV for submission</span>
                            </a>
                            <a href="<?php echo base_url(); ?>app/billing_reports/print_daily" class="list-group-item" target="_blank">
                                <i class="fa fa-print text-gray"></i> <strong>Print Daily Summary</strong>
                                <span class="pull-right text-muted">Print today's report</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment Method Breakdown -->
        <div class="row">
            <div class="col-md-6">
                <div class="box box-info">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-pie-chart"></i> Today's Collections by Method</h3>
                    </div>
                    <div class="box-body">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Payment Method</th>
                                    <th class="text-right">Amount (GH₵)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($daily_revenue['by_payment_method'])): ?>
                                    <?php foreach ($daily_revenue['by_payment_method'] as $method => $amount): ?>
                                    <tr>
                                        <td>
                                            <?php if ($method === 'CASH'): ?>
                                            <i class="fa fa-money text-green"></i>
                                            <?php elseif ($method === 'NHIS'): ?>
                                            <i class="fa fa-medkit text-blue"></i>
                                            <?php elseif ($method === 'MOMO' || $method === 'MOBILE_MONEY'): ?>
                                            <i class="fa fa-mobile text-yellow"></i>
                                            <?php else: ?>
                                            <i class="fa fa-credit-card text-purple"></i>
                                            <?php endif; ?>
                                            <?php echo htmlspecialchars($method); ?>
                                        </td>
                                        <td class="text-right"><strong><?php echo number_format($amount, 2); ?></strong></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="2" class="text-center text-muted">No collections today</td></tr>
                                <?php endif; ?>
                            </tbody>
                            <tfoot>
                                <tr class="success">
                                    <th>Total</th>
                                    <th class="text-right">GH₵ <?php echo number_format($daily_revenue['total_collected'], 2); ?></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="box box-warning">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-hospital-o"></i> Today's Revenue by Service</h3>
                    </div>
                    <div class="box-body">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Service Type</th>
                                    <th class="text-right">Amount (GH₵)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($daily_revenue['by_service_type'])): ?>
                                    <?php foreach ($daily_revenue['by_service_type'] as $service => $amount): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($service); ?></td>
                                        <td class="text-right"><?php echo number_format($amount, 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="2" class="text-center text-muted">No service revenue today</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Outstanding Aging -->
        <div class="row">
            <div class="col-md-12">
                <div class="box box-danger">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-clock-o"></i> Outstanding Bills Aging</h3>
                    </div>
                    <div class="box-body">
                        <div class="row">
                            <?php foreach ($outstanding['by_age'] as $range => $data): ?>
                            <div class="col-md-2 col-sm-4">
                                <div class="small-box <?php echo $range === '90+' ? 'bg-red' : ($range === '61-90' ? 'bg-orange' : ($range === '31-60' ? 'bg-yellow' : 'bg-aqua')); ?>">
                                    <div class="inner">
                                        <h3>GH₵<?php echo number_format($data['amount'], 0); ?></h3>
                                        <p><?php echo $data['count']; ?> bills (<?php echo $range; ?> days)</p>
                                    </div>
                                    <div class="icon">
                                        <i class="fa fa-file-text-o"></i>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<?php $this->load->view("admin/include/footer"); ?>
