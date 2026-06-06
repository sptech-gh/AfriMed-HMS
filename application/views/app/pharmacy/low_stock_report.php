<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Low Stock Report | Hospital Management System</title>
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
        <h1><i class="fa fa-warning"></i> Low Stock & Expiring Items Report</h1>
        <ol class="breadcrumb">
            <li><a href="<?= base_url() ?>app/pharmacy"><i class="fa fa-medkit"></i> Pharmacy</a></li>
            <li><a href="<?= base_url() ?>app/pharmacy/stores">Stores</a></li>
            <li class="active">Low Stock Report</li>
        </ol>
    </section>

    <section class="content">
        <?= isset($message) ? $message : '' ?>

        <!-- Summary -->
        <div class="row">
            <div class="col-md-6">
                <div class="info-box bg-yellow">
                    <span class="info-box-icon"><i class="fa fa-warning"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Low Stock Items</span>
                        <span class="info-box-number"><?= count($low_stock) ?></span>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="info-box bg-red">
                    <span class="info-box-icon"><i class="fa fa-clock-o"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Expiring Soon (90 days)</span>
                        <span class="info-box-number"><?= count($expiring) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Low Stock Items -->
        <div class="box box-warning">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-warning"></i> Low Stock Items</h3>
            </div>
            <div class="box-body">
                <?php if (!empty($low_stock)): ?>
                <table class="table table-bordered table-striped" id="lowStockTable">
                    <thead>
                        <tr>
                            <th>Store</th>
                            <th>Drug Name</th>
                            <th>Current Qty</th>
                            <th>Reorder Level</th>
                            <th>Shortage</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($low_stock as $item): 
                            $shortage = $item->reorder_level - $item->quantity;
                        ?>
                        <tr>
                            <td>
                                <span class="label label-info"><?= htmlspecialchars($item->store_code) ?></span>
                                <?= htmlspecialchars($item->store_name) ?>
                            </td>
                            <td><strong><?= htmlspecialchars($item->drug_name) ?></strong></td>
                            <td class="text-center">
                                <span class="text-red"><strong><?= number_format($item->quantity, 2) ?></strong></span>
                            </td>
                            <td class="text-center"><?= number_format($item->reorder_level) ?></td>
                            <td class="text-center">
                                <span class="label label-danger">-<?= number_format($shortage, 2) ?></span>
                            </td>
                            <td>
                                <a href="<?= base_url() ?>app/pharmacy/store_stock/<?= $item->store_id ?>" class="btn btn-xs btn-primary">
                                    <i class="fa fa-eye"></i> View Store
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="alert alert-success">
                    <i class="fa fa-check"></i> All stores have adequate stock levels.
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Expiring Items -->
        <div class="box box-danger">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-clock-o"></i> Items Expiring Within 90 Days</h3>
            </div>
            <div class="box-body">
                <?php if (!empty($expiring)): ?>
                <table class="table table-bordered table-striped" id="expiringTable">
                    <thead>
                        <tr>
                            <th>Store</th>
                            <th>Drug Name</th>
                            <th>Batch No</th>
                            <th>Quantity</th>
                            <th>Expiry Date</th>
                            <th>Days Left</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($expiring as $item): 
                            $daysLeft = floor((strtotime($item->expiry_date) - time()) / 86400);
                            $urgency = $daysLeft <= 30 ? 'danger' : ($daysLeft <= 60 ? 'warning' : 'info');
                        ?>
                        <tr class="<?= $daysLeft <= 30 ? 'danger' : '' ?>">
                            <td>
                                <span class="label label-info"><?= htmlspecialchars($item->store_code) ?></span>
                                <?= htmlspecialchars($item->store_name) ?>
                            </td>
                            <td><strong><?= htmlspecialchars($item->drug_name) ?></strong></td>
                            <td><?= htmlspecialchars($item->batch_no ?: '-') ?></td>
                            <td class="text-center"><?= number_format($item->quantity, 2) ?></td>
                            <td><?= date('d M Y', strtotime($item->expiry_date)) ?></td>
                            <td class="text-center">
                                <span class="label label-<?= $urgency ?>">
                                    <?= $daysLeft ?> days
                                </span>
                            </td>
                            <td>
                                <a href="<?= base_url() ?>app/pharmacy/store_stock/<?= $item->store_id ?>" class="btn btn-xs btn-primary">
                                    <i class="fa fa-eye"></i> View
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="alert alert-success">
                    <i class="fa fa-check"></i> No items expiring within the next 90 days.
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Navigation -->
        <div class="row">
            <div class="col-md-4">
                <a href="<?= base_url() ?>app/pharmacy/stores" class="btn btn-block btn-default">
                    <i class="fa fa-building"></i> Back to Stores
                </a>
            </div>
            <div class="col-md-4">
                <a href="<?= base_url() ?>app/pharmacy/transfers" class="btn btn-block btn-default">
                    <i class="fa fa-exchange"></i> Stock Transfers
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

<script>
$(function() {
    $('#lowStockTable').DataTable({
        "order": [[4, "desc"]],
        "pageLength": 25
    });
    $('#expiringTable').DataTable({
        "order": [[5, "asc"]],
        "pageLength": 25
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
