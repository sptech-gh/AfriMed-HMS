<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Billing Reconciliation | HMS</title>
    <meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>
    <link href="<?php echo base_url(); ?>bootstrap/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>font-awesome/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>dist/css/AdminLTE.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>dist/css/skins/skin-blue.min.css" rel="stylesheet" type="text/css" />
</head>
<body class="skin-blue">
<?php require_once(APPPATH.'views/include/header.php'); ?>
<div class="wrapper row-offcanvas row-offcanvas-left">
    <?php require_once(APPPATH.'views/include/sidebar.php'); ?>
    <aside class="right-side">
        <section class="content-header">
            <h1><i class="fa fa-balance-scale"></i> Billing Reconciliation <small>Single Source of Truth</small></h1>
            <ol class="breadcrumb">
                <li><a href="<?php echo base_url('app/dashboard'); ?>"><i class="fa fa-dashboard"></i> Dashboard</a></li>
                <li><a href="<?php echo base_url('app/billing'); ?>">Billing</a></li>
                <li class="active">Reconciliation</li>
            </ol>
        </section>

        <section class="content">
            <?php if ($this->session->flashdata('success')): ?>
            <div class="alert alert-success alert-dismissible">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                <i class="fa fa-check"></i> <?php echo $this->session->flashdata('success'); ?>
            </div>
            <?php endif; ?>
            <?php if ($this->session->flashdata('error')): ?>
            <div class="alert alert-danger alert-dismissible">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                <i class="fa fa-exclamation-triangle"></i> <?php echo $this->session->flashdata('error'); ?>
            </div>
            <?php endif; ?>
            <?php if ($this->session->flashdata('warning')): ?>
            <div class="alert alert-warning alert-dismissible">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                <i class="fa fa-warning"></i> <?php echo $this->session->flashdata('warning'); ?>
            </div>
            <?php endif; ?>

            <!-- Summary Cards -->
            <div class="row">
                <div class="col-lg-3 col-xs-6">
                    <div class="small-box bg-red">
                        <div class="inner">
                            <h3><?php echo isset($issue_counts['dispensed_not_billed']) ? $issue_counts['dispensed_not_billed'] : 0; ?></h3>
                            <p>Dispensed Not Billed</p>
                        </div>
                        <div class="icon"><i class="fa fa-exclamation-triangle"></i></div>
                    </div>
                </div>
                <div class="col-lg-3 col-xs-6">
                    <div class="small-box bg-yellow">
                        <div class="inner">
                            <h3><?php echo isset($issue_counts['billed_not_dispensed']) ? $issue_counts['billed_not_dispensed'] : 0; ?></h3>
                            <p>Billed Not Dispensed</p>
                        </div>
                        <div class="icon"><i class="fa fa-clock-o"></i></div>
                    </div>
                </div>
                <div class="col-lg-3 col-xs-6">
                    <div class="small-box bg-purple">
                        <div class="inner">
                            <h3><?php echo isset($issue_counts['paid_not_completed']) ? $issue_counts['paid_not_completed'] : 0; ?></h3>
                            <p>Paid Not Completed</p>
                        </div>
                        <div class="icon"><i class="fa fa-money"></i></div>
                    </div>
                </div>
                <div class="col-lg-3 col-xs-6">
                    <div class="small-box bg-aqua">
                        <div class="inner">
                            <h3><?php echo count($issues); ?></h3>
                            <p>Total Issues</p>
                        </div>
                        <div class="icon"><i class="fa fa-list"></i></div>
                    </div>
                </div>
            </div>

            <!-- Department Summaries -->
            <div class="row">
                <div class="col-md-6">
                    <div class="box box-primary">
                        <div class="box-header with-border">
                            <h3 class="box-title"><i class="fa fa-medkit"></i> Pharmacy Today</h3>
                        </div>
                        <div class="box-body">
                            <?php if ($pharmacy_summary): ?>
                            <table class="table table-condensed">
                                <tr><td>Total Transactions</td><td class="text-right"><strong><?php echo $pharmacy_summary->total_transactions; ?></strong></td></tr>
                                <tr><td>Total Amount</td><td class="text-right"><strong>GHS <?php echo number_format($pharmacy_summary->total_amount, 2); ?></strong></td></tr>
                                <tr class="success"><td>Collected</td><td class="text-right"><strong>GHS <?php echo number_format($pharmacy_summary->collected_amount, 2); ?></strong></td></tr>
                                <tr class="danger"><td>Outstanding</td><td class="text-right"><strong>GHS <?php echo number_format($pharmacy_summary->outstanding_amount, 2); ?></strong></td></tr>
                                <tr><td>NHIS Amount</td><td class="text-right"><strong>GHS <?php echo number_format($pharmacy_summary->nhis_amount, 2); ?></strong></td></tr>
                                <tr><td>Completed</td><td class="text-right"><strong><?php echo $pharmacy_summary->completed_count; ?></strong></td></tr>
                            </table>
                            <?php else: ?>
                            <p class="text-muted">No pharmacy transactions today</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="box box-info">
                        <div class="box-header with-border">
                            <h3 class="box-title"><i class="fa fa-flask"></i> Laboratory Today</h3>
                        </div>
                        <div class="box-body">
                            <?php if ($lab_summary): ?>
                            <table class="table table-condensed">
                                <tr><td>Total Transactions</td><td class="text-right"><strong><?php echo $lab_summary->total_transactions; ?></strong></td></tr>
                                <tr><td>Total Amount</td><td class="text-right"><strong>GHS <?php echo number_format($lab_summary->total_amount, 2); ?></strong></td></tr>
                                <tr class="success"><td>Collected</td><td class="text-right"><strong>GHS <?php echo number_format($lab_summary->collected_amount, 2); ?></strong></td></tr>
                                <tr class="danger"><td>Outstanding</td><td class="text-right"><strong>GHS <?php echo number_format($lab_summary->outstanding_amount, 2); ?></strong></td></tr>
                                <tr><td>NHIS Amount</td><td class="text-right"><strong>GHS <?php echo number_format($lab_summary->nhis_amount, 2); ?></strong></td></tr>
                                <tr><td>Completed</td><td class="text-right"><strong><?php echo $lab_summary->completed_count; ?></strong></td></tr>
                            </table>
                            <?php else: ?>
                            <p class="text-muted">No laboratory transactions today</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="row">
                <div class="col-md-12">
                    <div class="box box-default">
                        <div class="box-header with-border">
                            <h3 class="box-title"><i class="fa fa-cogs"></i> Actions</h3>
                        </div>
                        <div class="box-body">
                            <a href="<?php echo base_url('app/billing_reconciliation/run_check'); ?>" class="btn btn-primary">
                                <i class="fa fa-refresh"></i> Run Reconciliation Check
                            </a>
                            <a href="<?php echo base_url('app/billing_reconciliation/migrate_pharmacy?limit=500'); ?>" class="btn btn-warning">
                                <i class="fa fa-database"></i> Migrate Pharmacy Data
                            </a>
                            <a href="<?php echo base_url('app/billing_reconciliation/sync_payments?limit=500'); ?>" class="btn btn-success">
                                <i class="fa fa-money"></i> Sync Payments
                            </a>
                            <a href="<?php echo base_url('app/billing_reconciliation/full_migration'); ?>" class="btn btn-danger" onclick="return confirm('This will migrate all pending data. Continue?');">
                                <i class="fa fa-rocket"></i> Full Migration
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Issues Table -->
            <div class="row">
                <div class="col-md-12">
                    <div class="box box-danger">
                        <div class="box-header with-border">
                            <h3 class="box-title"><i class="fa fa-exclamation-circle"></i> Reconciliation Issues</h3>
                            <span class="badge bg-red pull-right"><?php echo count($issues); ?></span>
                        </div>
                        <div class="box-body table-responsive">
                            <?php if (empty($issues)): ?>
                            <div class="alert alert-success">
                                <i class="fa fa-check-circle"></i> No reconciliation issues found. All systems are in sync!
                            </div>
                            <?php else: ?>
                            <table class="table table-bordered table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Department</th>
                                        <th>Issue Type</th>
                                        <th>Reference</th>
                                        <th>Patient</th>
                                        <th>Details</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($issues as $issue): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($issue->recon_date)); ?></td>
                                        <td>
                                            <?php 
                                            $dept_class = 'default';
                                            if ($issue->department === 'PHARMACY') $dept_class = 'primary';
                                            elseif ($issue->department === 'LABORATORY') $dept_class = 'info';
                                            ?>
                                            <span class="label label-<?php echo $dept_class; ?>"><?php echo $issue->department; ?></span>
                                        </td>
                                        <td>
                                            <?php 
                                            $type_class = 'warning';
                                            if ($issue->issue_type === 'DISPENSED_NOT_BILLED') $type_class = 'danger';
                                            elseif ($issue->issue_type === 'PAID_NOT_COMPLETED') $type_class = 'purple';
                                            ?>
                                            <span class="label label-<?php echo $type_class; ?>"><?php echo str_replace('_', ' ', $issue->issue_type); ?></span>
                                        </td>
                                        <td><code><?php echo htmlspecialchars($issue->record_ref); ?></code></td>
                                        <td>
                                            <?php if ($issue->patient_no): ?>
                                            <a href="<?php echo base_url('app/billing_reconciliation/patient_ledger/' . $issue->patient_no); ?>">
                                                <?php echo $issue->patient_no; ?>
                                            </a>
                                            <?php else: ?>
                                            -
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($issue->details); ?></td>
                                        <td>
                                            <button type="button" class="btn btn-xs btn-success" data-toggle="modal" data-target="#resolveModal" data-id="<?php echo $issue->recon_id; ?>">
                                                <i class="fa fa-check"></i> Resolve
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </aside>
</div>

<!-- Resolve Modal -->
<div class="modal fade" id="resolveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?php echo base_url('app/billing_reconciliation/resolve_issue'); ?>">
                <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title"><i class="fa fa-check-circle"></i> Resolve Issue</h4>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="recon_id" id="resolve_recon_id">
                    <div class="form-group">
                        <label>Resolution Notes</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Describe how this issue was resolved..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="fa fa-check"></i> Mark Resolved</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="<?php echo base_url(); ?>jquery/jquery.min.js"></script>
<script src="<?php echo base_url(); ?>bootstrap/js/bootstrap.min.js"></script>
<script src="<?php echo base_url(); ?>dist/js/app.min.js"></script>
<script>
$('#resolveModal').on('show.bs.modal', function(e) {
    var id = $(e.relatedTarget).data('id');
    $('#resolve_recon_id').val(id);
});
</script>
</body>
</html>
