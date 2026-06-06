<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Store Stock | Hospital Management System</title>
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
        <h1><i class="fa fa-cubes"></i> <?= htmlspecialchars($store->store_name) ?> - Stock</h1>
        <ol class="breadcrumb">
            <li><a href="<?= base_url() ?>app/pharmacy"><i class="fa fa-medkit"></i> Pharmacy</a></li>
            <li><a href="<?= base_url() ?>app/pharmacy/stores">Stores</a></li>
            <li class="active"><?= htmlspecialchars($store->store_code) ?></li>
        </ol>
    </section>

    <section class="content">
        <?= isset($message) ? $message : '' ?>

        <!-- Store Info -->
        <div class="callout callout-info">
            <div class="row">
                <div class="col-md-3">
                    <strong>Store:</strong> <?= htmlspecialchars($store->store_name) ?>
                    <span class="label label-<?= $store->store_type === 'MAIN' ? 'success' : 'info' ?>"><?= $store->store_type ?></span>
                </div>
                <div class="col-md-3">
                    <strong>Location:</strong> <?= htmlspecialchars($store->location ?: 'N/A') ?>
                </div>
                <div class="col-md-3">
                    <strong>Hours:</strong> <?= htmlspecialchars($store->operating_hours ?: 'N/A') ?>
                </div>
                <div class="col-md-3">
                    <strong>Status:</strong>
                    <?= $store->is_active ? '<span class="label label-success">Active</span>' : '<span class="label label-default">Inactive</span>' ?>
                    <?= $store->can_dispense ? '<span class="label label-info">Can Dispense</span>' : '' ?>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="box box-default collapsed-box">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-filter"></i> Filters</h3>
                <div class="box-tools pull-right">
                    <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-plus"></i></button>
                </div>
            </div>
            <div class="box-body">
                <form method="GET" class="form-inline">
                    <div class="form-group">
                        <input type="text" name="search" class="form-control" placeholder="Search drug..." value="<?= htmlspecialchars($filters['search']) ?>">
                    </div>
                    <div class="form-group">
                        <label><input type="checkbox" name="low_stock_only" value="1" <?= $filters['low_stock_only'] ? 'checked' : '' ?>> Low Stock Only</label>
                    </div>
                    <div class="form-group">
                        <select name="expiring_soon" class="form-control">
                            <option value="">All Expiry</option>
                            <option value="30" <?= $filters['expiring_soon'] == 30 ? 'selected' : '' ?>>Expiring in 30 days</option>
                            <option value="60" <?= $filters['expiring_soon'] == 60 ? 'selected' : '' ?>>Expiring in 60 days</option>
                            <option value="90" <?= $filters['expiring_soon'] == 90 ? 'selected' : '' ?>>Expiring in 90 days</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> Filter</button>
                    <a href="<?= base_url() ?>app/pharmacy/store_stock/<?= $store->store_id ?>" class="btn btn-default">Clear</a>
                </form>
            </div>
        </div>

        <!-- Stock Table -->
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-list"></i> Stock Items (<?= count($stock) ?>)</h3>
                <div class="box-tools pull-right">
                    <button type="button" class="btn btn-success btn-sm" data-toggle="modal" data-target="#transferModal">
                        <i class="fa fa-exchange"></i> Request Transfer
                    </button>
                </div>
            </div>
            <div class="box-body">
                <table class="table table-bordered table-striped table-hover" id="stockTable">
                    <thead>
                        <tr>
                            <th>Drug Name</th>
                            <th>Batch No</th>
                            <th>Quantity</th>
                            <th>Reorder Level</th>
                            <th>Unit Cost</th>
                            <th>Expiry Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stock as $item): 
                            $isLow = $item->quantity <= $item->reorder_level;
                            $isExpiring = $item->expiry_date && strtotime($item->expiry_date) <= strtotime('+90 days');
                            $isExpired = $item->expiry_date && strtotime($item->expiry_date) < time();
                        ?>
                        <tr class="<?= $isExpired ? 'danger' : ($isLow ? 'warning' : '') ?>">
                            <td>
                                <strong><?= htmlspecialchars($item->drug_name ?: 'Unknown') ?></strong>
                                <?php if (isset($item->central_stock)): ?>
                                    <br><small class="text-muted">Central: <?= number_format($item->central_stock) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($item->batch_no ?: '-') ?></td>
                            <td>
                                <strong><?= number_format($item->quantity, 2) ?></strong>
                                <?php if ($isLow): ?>
                                    <span class="label label-warning"><i class="fa fa-warning"></i> Low</span>
                                <?php endif; ?>
                            </td>
                            <td><?= number_format($item->reorder_level) ?></td>
                            <td>GHS <?= number_format($item->unit_cost, 2) ?></td>
                            <td>
                                <?php if ($item->expiry_date): ?>
                                    <?= date('d M Y', strtotime($item->expiry_date)) ?>
                                    <?php if ($isExpired): ?>
                                        <span class="label label-danger">EXPIRED</span>
                                    <?php elseif ($isExpiring): ?>
                                        <span class="label label-warning">Expiring Soon</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($item->quantity > 0 && !$isExpired): ?>
                                    <span class="label label-success">In Stock</span>
                                <?php elseif ($isExpired): ?>
                                    <span class="label label-danger">Expired</span>
                                <?php else: ?>
                                    <span class="label label-default">Out of Stock</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button type="button" class="btn btn-xs btn-info btn-transfer" 
                                        data-drug-id="<?= $item->drug_id ?>"
                                        data-drug-name="<?= htmlspecialchars($item->drug_name) ?>"
                                        data-batch="<?= htmlspecialchars($item->batch_no) ?>"
                                        data-qty="<?= $item->quantity ?>"
                                        title="Transfer Out">
                                    <i class="fa fa-share"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($stock)): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted">No stock items found.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Navigation -->
        <div class="row">
            <div class="col-md-4">
                <a href="<?= base_url() ?>app/pharmacy/stores" class="btn btn-block btn-default">
                    <i class="fa fa-arrow-left"></i> Back to Stores
                </a>
            </div>
            <div class="col-md-4">
                <a href="<?= base_url() ?>app/pharmacy/transfers" class="btn btn-block btn-default">
                    <i class="fa fa-exchange"></i> View Transfers
                </a>
            </div>
            <div class="col-md-4">
                <a href="<?= base_url() ?>app/pharmacy" class="btn btn-block btn-default">
                    <i class="fa fa-medkit"></i> Pharmacy Worklist
                </a>
            </div>
        </div>
    </section>
</div>

<!-- Transfer Request Modal -->
<div class="modal fade" id="transferModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="<?= base_url() ?>app/pharmacy/transfer_request">
            <input type="hidden" name="from_store_id" value="<?= $store->store_id ?>">
            <input type="hidden" name="return_url" value="<?= base_url() ?>app/pharmacy/store_stock/<?= $store->store_id ?>">
            <div class="modal-content">
                <div class="modal-header bg-info">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title"><i class="fa fa-exchange"></i> Request Stock Transfer</h4>
                </div>
                <div class="modal-body">
                    <p class="text-muted">Transfer stock from <strong><?= htmlspecialchars($store->store_name) ?></strong> to another store.</p>
                    
                    <div class="form-group">
                        <label>Destination Store <span class="text-red">*</span></label>
                        <select name="to_store_id" id="to_store_id" class="form-control" required>
                            <option value="">-- Select Store --</option>
                            <?php foreach ($all_stores as $s): ?>
                                <?php if ($s->store_id != $store->store_id && $s->can_receive_transfers): ?>
                                <option value="<?= $s->store_id ?>"><?= htmlspecialchars($s->store_name) ?> (<?= $s->store_code ?>)</option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Drug <span class="text-red">*</span></label>
                        <select name="drug_id" id="transfer_drug_id" class="form-control" required>
                            <option value="">-- Select Drug --</option>
                            <?php foreach ($stock as $item): ?>
                                <?php if ($item->quantity > 0): ?>
                                <option value="<?= $item->drug_id ?>" data-max="<?= $item->quantity ?>" data-batch="<?= htmlspecialchars($item->batch_no) ?>">
                                    <?= htmlspecialchars($item->drug_name) ?> (Qty: <?= number_format($item->quantity) ?>)
                                </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Quantity <span class="text-red">*</span></label>
                                <input type="number" name="quantity" id="transfer_qty" class="form-control" required min="0.01" step="0.01">
                                <small class="text-muted">Max: <span id="max_qty">0</span></small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Batch No</label>
                                <input type="text" name="batch_no" id="transfer_batch" class="form-control" readonly>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Reason for transfer..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info"><i class="fa fa-exchange"></i> Request Transfer</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
$(function() {
    $('#stockTable').DataTable({
        "order": [[0, "asc"]],
        "pageLength": 50
    });

    $('#transfer_drug_id').change(function() {
        var opt = $(this).find(':selected');
        var max = opt.data('max') || 0;
        var batch = opt.data('batch') || '';
        $('#max_qty').text(max);
        $('#transfer_qty').attr('max', max);
        $('#transfer_batch').val(batch);
    });

    $('.btn-transfer').click(function() {
        var drugId = $(this).data('drug-id');
        var drugName = $(this).data('drug-name');
        var batch = $(this).data('batch');
        var qty = $(this).data('qty');
        
        $('#transfer_drug_id').val(drugId).trigger('change');
        $('#transferModal').modal('show');
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
