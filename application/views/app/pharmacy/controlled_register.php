<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Controlled Register | Hospital Management System</title>
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
        <h1><i class="fa fa-book"></i> Controlled Drug Register</h1>
        <ol class="breadcrumb">
            <li><a href="<?= base_url() ?>app/pharmacy"><i class="fa fa-medkit"></i> Pharmacy</a></li>
            <li><a href="<?= base_url() ?>app/pharmacy/controlled_drugs">Controlled Drugs</a></li>
            <li class="active">Register</li>
        </ol>
    </section>

    <section class="content">
        <?= isset($message) ? $message : '' ?>

        <!-- Filters -->
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-filter"></i> Filter Register</h3>
            </div>
            <div class="box-body">
                <form method="GET" class="form-inline">
                    <div class="form-group">
                        <select name="drug_id" class="form-control">
                            <option value="">All Drugs</option>
                            <?php foreach ($drugs as $d): ?>
                            <option value="<?= $d->drug_id ?>" <?= $filters['drug_id'] == $d->drug_id ? 'selected' : '' ?>>
                                <?= htmlspecialchars($d->drug_name) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <select name="store_id" class="form-control">
                            <option value="">All Stores</option>
                            <?php foreach ($stores as $s): ?>
                            <option value="<?= $s->store_id ?>" <?= $filters['store_id'] == $s->store_id ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s->store_name) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <select name="transaction_type" class="form-control">
                            <option value="">All Transactions</option>
                            <option value="RECEIPT" <?= $filters['transaction_type'] === 'RECEIPT' ? 'selected' : '' ?>>Receipt</option>
                            <option value="DISPENSE" <?= $filters['transaction_type'] === 'DISPENSE' ? 'selected' : '' ?>>Dispense</option>
                            <option value="ADJUSTMENT" <?= $filters['transaction_type'] === 'ADJUSTMENT' ? 'selected' : '' ?>>Adjustment</option>
                            <option value="DESTRUCTION" <?= $filters['transaction_type'] === 'DESTRUCTION' ? 'selected' : '' ?>>Destruction</option>
                            <option value="TRANSFER_IN" <?= $filters['transaction_type'] === 'TRANSFER_IN' ? 'selected' : '' ?>>Transfer In</option>
                            <option value="TRANSFER_OUT" <?= $filters['transaction_type'] === 'TRANSFER_OUT' ? 'selected' : '' ?>>Transfer Out</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($filters['date_from']) ?>" placeholder="From">
                    </div>
                    <div class="form-group">
                        <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($filters['date_to']) ?>" placeholder="To">
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> Filter</button>
                    <a href="<?= base_url() ?>app/pharmacy/controlled_register" class="btn btn-default">Clear</a>
                </form>
            </div>
        </div>

        <!-- Register Table -->
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-list"></i> Transaction Log (<?= count($register) ?>)</h3>
                <div class="box-tools pull-right">
                    <button type="button" class="btn btn-sm btn-default" onclick="window.print()">
                        <i class="fa fa-print"></i> Print
                    </button>
                </div>
            </div>
            <div class="box-body">
                <table class="table table-bordered table-striped table-condensed" id="registerTable">
                    <thead>
                        <tr>
                            <th>Date/Time</th>
                            <th>Drug</th>
                            <th>Store</th>
                            <th>Type</th>
                            <th>In</th>
                            <th>Out</th>
                            <th>Balance</th>
                            <th>Patient/Supplier</th>
                            <th>Recorded By</th>
                            <th>Witness</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($register as $r): ?>
                        <tr>
                            <td>
                                <?= date('d M Y', strtotime($r->recorded_at)) ?>
                                <br><small class="text-muted"><?= date('H:i:s', strtotime($r->recorded_at)) ?></small>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($r->drug_name) ?></strong>
                                <?php if ($r->batch_no): ?>
                                    <br><small class="text-muted">Batch: <?= htmlspecialchars($r->batch_no) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($r->store_name ?: 'Main') ?></td>
                            <td>
                                <?php
                                $typeClass = array(
                                    'RECEIPT' => 'success',
                                    'DISPENSE' => 'danger',
                                    'ADJUSTMENT' => 'warning',
                                    'DESTRUCTION' => 'danger',
                                    'TRANSFER_IN' => 'info',
                                    'TRANSFER_OUT' => 'primary'
                                );
                                $cls = isset($typeClass[$r->transaction_type]) ? $typeClass[$r->transaction_type] : 'default';
                                ?>
                                <span class="label label-<?= $cls ?>"><?= $r->transaction_type ?></span>
                            </td>
                            <td class="text-right text-success">
                                <?= $r->quantity_in > 0 ? '+' . number_format($r->quantity_in, 2) : '' ?>
                            </td>
                            <td class="text-right text-danger">
                                <?= $r->quantity_out > 0 ? '-' . number_format($r->quantity_out, 2) : '' ?>
                            </td>
                            <td class="text-right">
                                <small class="text-muted"><?= number_format($r->balance_before, 2) ?> →</small>
                                <strong><?= number_format($r->balance_after, 2) ?></strong>
                            </td>
                            <td>
                                <?php if ($r->patient_no): ?>
                                    <i class="fa fa-user"></i> <?= htmlspecialchars($r->patient_name ?: $r->patient_no) ?>
                                <?php elseif ($r->supplier_name): ?>
                                    <i class="fa fa-truck"></i> <?= htmlspecialchars($r->supplier_name) ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($r->recorded_by) ?></td>
                            <td>
                                <?php if ($r->witnessed_by): ?>
                                    <i class="fa fa-eye text-info"></i> <?= htmlspecialchars($r->witnessed_by) ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Navigation -->
        <div class="row">
            <div class="col-md-4">
                <a href="<?= base_url() ?>app/pharmacy/controlled_drugs" class="btn btn-block btn-default">
                    <i class="fa fa-shield"></i> Controlled Drugs List
                </a>
            </div>
            <div class="col-md-4">
                <a href="<?= base_url() ?>app/pharmacy/pending_authorizations" class="btn btn-block btn-warning">
                    <i class="fa fa-clock-o"></i> Pending Authorizations
                </a>
            </div>
            <div class="col-md-4">
                <a href="<?= base_url() ?>app/pharmacy" class="btn btn-block btn-default">
                    <i class="fa fa-arrow-left"></i> Back to Worklist
                </a>
            </div>
        </div>
    </section>
</div>

<script>
$(function() {
    $('#registerTable').DataTable({
        "order": [[0, "desc"]],
        "pageLength": 50
    });
});
</script>

<style>
@media print {
    .content-header, .box-tools, .btn, .sidebar, .main-header, .main-footer { display: none !important; }
    .content-wrapper { margin-left: 0 !important; }
    .box { border: none !important; }
}
</style>
    </aside>
</div>

<script src="<?php echo base_url(); ?>public/js/jquery.min.js"></script>
<script src="<?php echo base_url(); ?>public/js/bootstrap.min.js"></script>
<script src="<?php echo base_url(); ?>public/js/plugins/datatables/jquery.dataTables.js"></script>
<script src="<?php echo base_url(); ?>public/js/plugins/datatables/dataTables.bootstrap.js"></script>
<script src="<?php echo base_url(); ?>public/js/AdminLTE/app.js"></script>
</body>
</html>
