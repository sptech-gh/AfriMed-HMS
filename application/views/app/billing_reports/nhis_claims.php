<?php $this->load->view("admin/include/header"); ?>
<?php $this->load->view("admin/include/sidebar"); ?>

<div class="content-wrapper">
    <section class="content-header">
        <h1>
            <i class="fa fa-medkit"></i> NHIS Claims Report
            <small>Insurance Claims Summary</small>
        </h1>
        <ol class="breadcrumb">
            <li><a href="<?php echo base_url(); ?>app/dashboard"><i class="fa fa-dashboard"></i> Dashboard</a></li>
            <li><a href="<?php echo base_url(); ?>app/billing_reports">Billing Reports</a></li>
            <li class="active">NHIS Claims</li>
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
                    <button type="submit" class="btn btn-primary" style="margin-left: 15px;">
                        <i class="fa fa-search"></i> Generate
                    </button>
                    <a href="<?php echo base_url(); ?>app/billing_reports/export_nhis_claims?from=<?php echo $from_date; ?>&to=<?php echo $to_date; ?>" 
                       class="btn btn-success" style="margin-left: 10px;">
                        <i class="fa fa-download"></i> Export CSV
                    </a>
                </form>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row">
            <div class="col-md-3 col-sm-6">
                <div class="info-box bg-aqua">
                    <span class="info-box-icon"><i class="fa fa-file-text-o"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Total Claims</span>
                        <span class="info-box-number"><?php echo number_format($report['total_claims']); ?></span>
                        <span class="progress-description">GH₵ <?php echo number_format($report['total_amount'], 2); ?></span>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="info-box bg-yellow">
                    <span class="info-box-icon"><i class="fa fa-clock-o"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Pending</span>
                        <span class="info-box-number"><?php echo number_format($report['pending_claims']); ?></span>
                        <span class="progress-description">GH₵ <?php echo number_format($report['pending_amount'], 2); ?></span>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="info-box bg-green">
                    <span class="info-box-icon"><i class="fa fa-check"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Approved</span>
                        <span class="info-box-number"><?php echo number_format($report['approved_claims']); ?></span>
                        <span class="progress-description">GH₵ <?php echo number_format($report['approved_amount'], 2); ?></span>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="info-box bg-red">
                    <span class="info-box-icon"><i class="fa fa-times"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Rejected</span>
                        <span class="info-box-number"><?php echo number_format($report['rejected_claims']); ?></span>
                        <span class="progress-description">GH₵ <?php echo number_format($report['rejected_amount'], 2); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Claims by Service Type -->
        <div class="row">
            <div class="col-md-6">
                <div class="box box-info">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-pie-chart"></i> Claims by Service Type</h3>
                    </div>
                    <div class="box-body">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Service Type</th>
                                    <th class="text-center">Claims</th>
                                    <th class="text-right">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($report['by_service_type'])): ?>
                                    <?php foreach ($report['by_service_type'] as $type => $data): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($type); ?></td>
                                        <td class="text-center"><?php echo number_format($data['count']); ?></td>
                                        <td class="text-right">GH₵ <?php echo number_format($data['amount'], 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="3" class="text-center text-muted">No claims in this period</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="box box-success">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-info-circle"></i> NHIS Submission Guidelines</h3>
                    </div>
                    <div class="box-body">
                        <div class="callout callout-info">
                            <h4><i class="fa fa-info"></i> Before Submission</h4>
                            <p>Ensure all claims have:</p>
                            <ul>
                                <li>Valid NHIS number for patient</li>
                                <li>Correct service codes</li>
                                <li>Accurate dates of service</li>
                                <li>Supporting documentation attached</li>
                            </ul>
                        </div>
                        <div class="callout callout-warning">
                            <h4><i class="fa fa-clock-o"></i> Submission Deadline</h4>
                            <p>Submit claims within 30 days of service date to avoid rejection.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Claims List -->
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-list"></i> Claims List (<?php echo $from_date; ?> to <?php echo $to_date; ?>)</h3>
            </div>
            <div class="box-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover" id="claimsTable">
                        <thead>
                            <tr>
                                <th>Bill No</th>
                                <th>Date</th>
                                <th>Patient</th>
                                <th>Service</th>
                                <th class="text-right">Amount</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($report['claims_list'])): ?>
                                <?php foreach ($report['claims_list'] as $claim): ?>
                                <tr>
                                    <td><code><?php echo htmlspecialchars($claim->bill_no ?? '-'); ?></code></td>
                                    <td><?php echo date('M d, Y', strtotime($claim->created_at)); ?></td>
                                    <td><?php echo htmlspecialchars($claim->patient_no ?? '-'); ?></td>
                                    <td>
                                        <span class="label label-info"><?php echo htmlspecialchars($claim->service_type); ?></span>
                                        <?php echo htmlspecialchars($claim->service_name); ?>
                                    </td>
                                    <td class="text-right">GH₵ <?php echo number_format($claim->line_total, 2); ?></td>
                                    <td class="text-center">
                                        <?php 
                                        $status = strtoupper($claim->payment_status ?? 'PENDING');
                                        $badge = 'default';
                                        if ($status === 'PAID') $badge = 'success';
                                        elseif ($status === 'PENDING') $badge = 'warning';
                                        elseif ($status === 'REJECTED') $badge = 'danger';
                                        ?>
                                        <span class="label label-<?php echo $badge; ?>"><?php echo $status; ?></span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="text-center text-muted">No NHIS claims found in this period</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>

<script>
$(document).ready(function() {
    if ($.fn.DataTable) {
        $('#claimsTable').DataTable({
            pageLength: 25,
            order: [[1, 'desc']]
        });
    }
});
</script>

<?php $this->load->view("admin/include/footer"); ?>
