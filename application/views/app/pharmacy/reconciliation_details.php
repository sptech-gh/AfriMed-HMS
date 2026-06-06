<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Reconciliation Details | Hospital Management System</title>
    <meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>
    <link href="<?php echo base_url(); ?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/css/datatables/dataTables.bootstrap.css" rel="stylesheet" type="text/css" />
</head>
<body class="skin-blue">
<?php require_once(APPPATH.'views/include/header.php');?>

<div class="wrapper row-offcanvas row-offcanvas-left">
    <?php require_once(APPPATH.'views/include/sidebar.php');?>
    
    <aside class="right-side">
    <section class="content-header">
        <h1><i class="fa fa-calculator"></i> Reconciliation Details</h1>
        <ol class="breadcrumb">
            <li><a href="<?= base_url() ?>app/pharmacy"><i class="fa fa-medkit"></i> Pharmacy</a></li>
            <li><a href="<?= base_url() ?>app/pharmacy/reconciliations">Reconciliations</a></li>
            <li class="active">Details</li>
        </ol>
    </section>

    <section class="content">
        <?= isset($message) ? $message : '' ?>

        <!-- Reconciliation Header -->
        <?php $status_info = isset($recon_statuses[$recon->status]) ? $recon_statuses[$recon->status] : array('label' => $recon->status, 'class' => 'default'); ?>
        <div class="box box-<?= $status_info['class'] ?>">
            <div class="box-header with-border">
                <h3 class="box-title">
                    <i class="fa fa-calendar"></i> 
                    <?= date('d M Y', strtotime($recon->period_start)) ?> - <?= date('d M Y', strtotime($recon->period_end)) ?>
                    (<?= $recon->reconciliation_type ?>)
                </h3>
                <div class="box-tools pull-right">
                    <span class="label label-<?= $status_info['class'] ?>"><?= $status_info['label'] ?></span>
                </div>
            </div>
            <div class="box-body">
                <div class="row">
                    <div class="col-md-2 text-center">
                        <h4 class="text-blue">GHS <?= number_format($recon->total_sales, 2) ?></h4>
                        <small>Total Sales</small>
                    </div>
                    <div class="col-md-2 text-center">
                        <h4 class="text-red">GHS <?= number_format($recon->total_cost, 2) ?></h4>
                        <small>Total Cost</small>
                    </div>
                    <div class="col-md-2 text-center">
                        <h4 class="text-green">GHS <?= number_format($recon->gross_profit, 2) ?></h4>
                        <small>Gross Profit</small>
                    </div>
                    <div class="col-md-2 text-center">
                        <h4><?= number_format($recon->total_dispensed_qty) ?></h4>
                        <small>Items Dispensed</small>
                    </div>
                    <div class="col-md-2 text-center">
                        <h4 class="text-<?= $recon->variance < 0 ? 'red' : ($recon->variance > 0 ? 'green' : 'muted') ?>">
                            GHS <?= number_format($recon->variance, 2) ?>
                        </h4>
                        <small>Cash Variance</small>
                    </div>
                    <div class="col-md-2 text-center">
                        <h4><?= $recon->discrepancies_count ?></h4>
                        <small>Discrepancies</small>
                    </div>
                </div>
            </div>
            <div class="box-footer">
                <div class="row">
                    <div class="col-md-4">
                        <strong>Store:</strong> <?= htmlspecialchars($recon->store_name ?: 'All Stores') ?>
                    </div>
                    <div class="col-md-4">
                        <strong>Created:</strong> <?= date('d M Y H:i', strtotime($recon->created_at)) ?> by <?= htmlspecialchars($recon->created_by) ?>
                    </div>
                    <div class="col-md-4">
                        <?php if ($recon->approved_by): ?>
                        <strong>Approved:</strong> <?= date('d M Y H:i', strtotime($recon->approved_at)) ?> by <?= htmlspecialchars($recon->approved_by) ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cash Reconciliation -->
        <div class="box box-warning">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-money"></i> Cash Reconciliation</h3>
            </div>
            <div class="box-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Expected Cash (from sales)</label>
                            <input type="text" class="form-control" value="GHS <?= number_format($recon->expected_cash, 2) ?>" readonly>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Actual Cash Collected</label>
                            <input type="text" class="form-control" value="GHS <?= number_format($recon->actual_cash, 2) ?>" readonly>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Variance</label>
                            <input type="text" class="form-control text-<?= $recon->variance < 0 ? 'red' : 'green' ?>" 
                                   value="GHS <?= number_format($recon->variance, 2) ?>" readonly>
                        </div>
                    </div>
                </div>
                <?php if ($recon->status === 'DRAFT'): ?>
                <hr>
                <form method="POST" action="<?= base_url() ?>app/pharmacy/update_actual_cash/<?= $recon->reconciliation_id ?>" class="form-inline">
                    <div class="form-group">
                        <label>Enter Actual Cash:</label>
                        <input type="number" step="0.01" name="actual_cash" class="form-control" placeholder="0.00" required>
                    </div>
                    <div class="form-group">
                        <input type="text" name="notes" class="form-control" placeholder="Notes (if variance)">
                    </div>
                    <button type="submit" class="btn btn-warning"><i class="fa fa-save"></i> Update</button>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Items -->
        <div class="box box-info">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-list"></i> Dispensed Items (<?= count($items) ?>)</h3>
                <div class="box-tools pull-right">
                    <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button>
                </div>
            </div>
            <div class="box-body">
                <?php if (!empty($items)): ?>
                <table class="table table-bordered table-condensed" id="itemsTable">
                    <thead>
                        <tr>
                            <th>Drug</th>
                            <th>Qty Dispensed</th>
                            <th>Unit Cost</th>
                            <th>Unit Price</th>
                            <th>Total Cost</th>
                            <th>Total Sales</th>
                            <th>Profit</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?= htmlspecialchars($item->drug_name) ?></td>
                            <td><?= number_format($item->dispensed_qty, 2) ?></td>
                            <td>GHS <?= number_format($item->unit_cost, 2) ?></td>
                            <td>GHS <?= number_format($item->unit_price, 2) ?></td>
                            <td>GHS <?= number_format($item->total_cost, 2) ?></td>
                            <td>GHS <?= number_format($item->total_sales, 2) ?></td>
                            <td class="text-green">GHS <?= number_format($item->total_sales - $item->total_cost, 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="bg-gray">
                            <th>TOTAL</th>
                            <th><?= number_format(array_sum(array_column($items, 'dispensed_qty')), 2) ?></th>
                            <th>-</th>
                            <th>-</th>
                            <th>GHS <?= number_format(array_sum(array_column($items, 'total_cost')), 2) ?></th>
                            <th>GHS <?= number_format(array_sum(array_column($items, 'total_sales')), 2) ?></th>
                            <th class="text-green">GHS <?= number_format($recon->gross_profit, 2) ?></th>
                        </tr>
                    </tfoot>
                </table>
                <?php else: ?>
                <div class="alert alert-info">No items in this reconciliation period.</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Discrepancies -->
        <?php if (!empty($discrepancies)): ?>
        <div class="box box-danger">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-exclamation-triangle"></i> Discrepancies (<?= count($discrepancies) ?>)</h3>
            </div>
            <div class="box-body">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Expected</th>
                            <th>Actual</th>
                            <th>Variance</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($discrepancies as $d): ?>
                        <tr>
                            <td>
                                <?= htmlspecialchars($d->discrepancy_type) ?>
                                <?php if ($d->drug_name): ?>
                                    <br><small><?= htmlspecialchars($d->drug_name) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>GHS <?= number_format($d->expected_value, 2) ?></td>
                            <td>GHS <?= number_format($d->actual_value, 2) ?></td>
                            <td class="text-<?= $d->variance < 0 ? 'red' : 'green' ?>">
                                GHS <?= number_format($d->variance, 2) ?>
                            </td>
                            <td>
                                <?php if ($d->status === 'RESOLVED'): ?>
                                    <span class="label label-success">Resolved</span>
                                <?php else: ?>
                                    <span class="label label-warning">Open</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($d->status !== 'RESOLVED'): ?>
                                <button type="button" class="btn btn-xs btn-success btn-resolve" 
                                        data-discrepancy-id="<?= $d->discrepancy_id ?>">
                                    <i class="fa fa-check"></i> Resolve
                                </button>
                                <?php else: ?>
                                <small class="text-muted"><?= htmlspecialchars($d->resolution) ?></small>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Actions -->
        <div class="box box-default">
            <div class="box-body">
                <div class="row">
                    <?php if ($recon->status === 'DRAFT'): ?>
                    <div class="col-md-4">
                        <a href="<?= base_url() ?>app/pharmacy/submit_reconciliation/<?= $recon->reconciliation_id ?>" 
                           class="btn btn-block btn-warning" onclick="return confirm('Submit for approval?')">
                            <i class="fa fa-send"></i> Submit for Approval
                        </a>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($recon->status === 'PENDING_APPROVAL' && $this->session->userdata('role') === 'admin'): ?>
                    <div class="col-md-4">
                        <a href="<?= base_url() ?>app/pharmacy/approve_reconciliation/<?= $recon->reconciliation_id ?>" 
                           class="btn btn-block btn-info" onclick="return confirm('Approve this reconciliation?')">
                            <i class="fa fa-check"></i> Approve
                        </a>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($recon->status === 'APPROVED' && $this->session->userdata('role') === 'admin'): ?>
                    <div class="col-md-4">
                        <a href="<?= base_url() ?>app/pharmacy/finalize_reconciliation/<?= $recon->reconciliation_id ?>" 
                           class="btn btn-block btn-success" onclick="return confirm('Finalize this reconciliation? This cannot be undone.')">
                            <i class="fa fa-lock"></i> Finalize
                        </a>
                    </div>
                    <?php endif; ?>
                    
                    <div class="col-md-4">
                        <button type="button" class="btn btn-block btn-default" onclick="window.print()">
                            <i class="fa fa-print"></i> Print Report
                        </button>
                    </div>
                    
                    <div class="col-md-4">
                        <a href="<?= base_url() ?>app/pharmacy/reconciliations" class="btn btn-block btn-default">
                            <i class="fa fa-arrow-left"></i> Back to List
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- Resolve Discrepancy Modal -->
<div class="modal fade" id="resolveModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="<?= base_url() ?>app/pharmacy/resolve_discrepancy">
            <input type="hidden" name="reconciliation_id" value="<?= $recon->reconciliation_id ?>">
            <input type="hidden" name="discrepancy_id" id="resolve_discrepancy_id">
            <div class="modal-content">
                <div class="modal-header bg-green">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title"><i class="fa fa-check"></i> Resolve Discrepancy</h4>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Resolution Notes <span class="text-red">*</span></label>
                        <textarea name="resolution" class="form-control" rows="3" required placeholder="Explain how this discrepancy was resolved..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="fa fa-check"></i> Mark Resolved</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
$(function() {
    $('#itemsTable').DataTable({
        "order": [[5, "desc"]],
        "pageLength": 25
    });

    $('.btn-resolve').click(function() {
        var discrepancyId = $(this).data('discrepancy-id');
        $('#resolve_discrepancy_id').val(discrepancyId);
        $('#resolveModal').modal('show');
    });
});
</script>
    </aside>
</div>

<script src="<?php echo base_url(); ?>public/js/jquery.min.js"></script>
<script src="<?php echo base_url(); ?>public/js/bootstrap.min.js"></script>
<script src="<?php echo base_url(); ?>public/js/plugins/datatables/jquery.dataTables.js"></script>
<script src="<?php echo base_url(); ?>public/js/plugins/datatables/dataTables.bootstrap.js"></script>
<script src="<?php echo base_url(); ?>public/js/AdminLTE/app.js"></script>
</body>
</html>
