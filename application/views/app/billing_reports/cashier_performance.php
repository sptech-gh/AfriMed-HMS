<?php $this->load->view("admin/include/header"); ?>
<?php $this->load->view("admin/include/sidebar"); ?>

<div class="content-wrapper">
    <section class="content-header">
        <h1>
            <i class="fa fa-users"></i> Cashier Performance Report
            <small>Collections by Cashier</small>
        </h1>
        <ol class="breadcrumb">
            <li><a href="<?php echo base_url(); ?>app/dashboard"><i class="fa fa-dashboard"></i> Dashboard</a></li>
            <li><a href="<?php echo base_url(); ?>app/billing_reports">Billing Reports</a></li>
            <li class="active">Cashier Performance</li>
        </ol>
    </section>

    <section class="content">
        <!-- Date Filter -->
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-filter"></i> Filter</h3>
            </div>
            <div class="box-body">
                <form method="get" class="form-inline">
                    <div class="form-group">
                        <label>From:</label>
                        <input type="date" name="from" class="form-control" value="<?php echo $from_date; ?>">
                    </div>
                    <div class="form-group" style="margin-left: 15px;">
                        <label>To:</label>
                        <input type="date" name="to" class="form-control" value="<?php echo $to_date; ?>">
                    </div>
                    <div class="form-group" style="margin-left: 15px;">
                        <label>Cashier:</label>
                        <select name="cashier" class="form-control">
                            <option value="">All Cashiers</option>
                            <?php foreach ($cashiers as $c): ?>
                            <option value="<?php echo $c->user_id; ?>"><?php echo htmlspecialchars($c->cName); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary" style="margin-left: 15px;">
                        <i class="fa fa-search"></i> Generate
                    </button>
                </form>
            </div>
        </div>

        <!-- Performance Table -->
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-table"></i> Cashier Collections Summary</h3>
            </div>
            <div class="box-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Cashier</th>
                                <th class="text-center">Transactions</th>
                                <th class="text-right">Cash (GH₵)</th>
                                <th class="text-right">NHIS (GH₵)</th>
                                <th class="text-right">Other (GH₵)</th>
                                <th class="text-right">Total (GH₵)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $grand_total = 0;
                            $grand_cash = 0;
                            $grand_nhis = 0;
                            $grand_other = 0;
                            $grand_txn = 0;
                            
                            if (!empty($report['cashiers'])): 
                                foreach ($report['cashiers'] as $c): 
                                    $grand_total += $c['total_collected'];
                                    $grand_cash += $c['cash_total'];
                                    $grand_nhis += $c['nhis_total'];
                                    $grand_other += $c['other_total'];
                                    $grand_txn += $c['transaction_count'];
                            ?>
                            <tr>
                                <td>
                                    <i class="fa fa-user text-blue"></i>
                                    <strong><?php echo htmlspecialchars($c['cashier_name']); ?></strong>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-aqua"><?php echo number_format($c['transaction_count']); ?></span>
                                </td>
                                <td class="text-right"><?php echo number_format($c['cash_total'], 2); ?></td>
                                <td class="text-right"><?php echo number_format($c['nhis_total'], 2); ?></td>
                                <td class="text-right"><?php echo number_format($c['other_total'], 2); ?></td>
                                <td class="text-right"><strong><?php echo number_format($c['total_collected'], 2); ?></strong></td>
                            </tr>
                            <?php 
                                endforeach; 
                            else: 
                            ?>
                                <tr><td colspan="6" class="text-center text-muted">No cashier data found</td></tr>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr class="success">
                                <th>TOTAL</th>
                                <th class="text-center"><?php echo number_format($grand_txn); ?></th>
                                <th class="text-right">GH₵ <?php echo number_format($grand_cash, 2); ?></th>
                                <th class="text-right">GH₵ <?php echo number_format($grand_nhis, 2); ?></th>
                                <th class="text-right">GH₵ <?php echo number_format($grand_other, 2); ?></th>
                                <th class="text-right">GH₵ <?php echo number_format($grand_total, 2); ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <!-- Visual Comparison -->
        <?php if (!empty($report['cashiers']) && count($report['cashiers']) > 1): ?>
        <div class="box box-info">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-bar-chart"></i> Performance Comparison</h3>
            </div>
            <div class="box-body">
                <?php foreach ($report['cashiers'] as $c): 
                    $pct = $grand_total > 0 ? ($c['total_collected'] / $grand_total) * 100 : 0;
                ?>
                <div class="progress-group">
                    <span class="progress-text"><?php echo htmlspecialchars($c['cashier_name']); ?></span>
                    <span class="progress-number"><strong>GH₵<?php echo number_format($c['total_collected'], 2); ?></strong></span>
                    <div class="progress">
                        <div class="progress-bar progress-bar-aqua" style="width: <?php echo round($pct); ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </section>
</div>

<?php $this->load->view("admin/include/footer"); ?>
