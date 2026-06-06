<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Stock Transfers | Hospital Management System</title>
    <meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
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
        <h1><i class="fa fa-exchange"></i> Stock Transfers</h1>
        <ol class="breadcrumb">
            <li><a href="<?= base_url() ?>app/pharmacy"><i class="fa fa-medkit"></i> Pharmacy</a></li>
            <li><a href="<?= base_url() ?>app/pharmacy/stores">Stores</a></li>
            <li class="active">Transfers</li>
        </ol>
    </section>

    <section class="content">
        <?= isset($message) ? $message : '' ?>

        <?php if ($user_store): ?>
        <div class="callout callout-info">
            <strong>Your Store:</strong> <?= htmlspecialchars($user_store->store_name) ?> (<?= $user_store->store_code ?>)
        </div>
        <?php endif; ?>

        <!-- Pending Incoming Transfers -->
        <?php if (!empty($pending_incoming)): ?>
        <div class="box box-success">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-download"></i> Incoming Transfers (<?= count($pending_incoming) ?>)</h3>
            </div>
            <div class="box-body">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Transfer #</th>
                            <th>From Store</th>
                            <th>Drug</th>
                            <th>Quantity</th>
                            <th>Status</th>
                            <th>Requested</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_incoming as $t): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($t->transfer_no) ?></strong></td>
                            <td><?= htmlspecialchars($t->from_store_name) ?></td>
                            <td><?= htmlspecialchars($t->drug_name) ?> <?= $t->batch_no ? "({$t->batch_no})" : '' ?></td>
                            <td><?= number_format($t->quantity, 2) ?></td>
                            <td><span class="label label-info"><?= $t->status ?></span></td>
                            <td><?= date('d M Y H:i', strtotime($t->requested_at)) ?></td>
                            <td>
                                <?php if ($t->status === 'IN_TRANSIT' || $t->status === 'APPROVED'): ?>
                                <form method="POST" action="<?= base_url() ?>app/pharmacy/transfer_receive/<?= $t->transfer_id ?>" style="display:inline;">
                                    <button type="submit" class="btn btn-xs btn-success" onclick="return confirm('Confirm receipt of this transfer?')">
                                        <i class="fa fa-check"></i> Receive
                                    </button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Pending Outgoing Transfers -->
        <?php if (!empty($pending_outgoing)): ?>
        <div class="box box-warning">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-upload"></i> Outgoing Transfers Awaiting Approval (<?= count($pending_outgoing) ?>)</h3>
            </div>
            <div class="box-body">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Transfer #</th>
                            <th>To Store</th>
                            <th>Drug</th>
                            <th>Quantity</th>
                            <th>Requested</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_outgoing as $t): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($t->transfer_no) ?></strong></td>
                            <td><?= htmlspecialchars($t->to_store_name) ?></td>
                            <td><?= htmlspecialchars($t->drug_name) ?> <?= $t->batch_no ? "({$t->batch_no})" : '' ?></td>
                            <td><?= number_format($t->quantity, 2) ?></td>
                            <td><?= date('d M Y H:i', strtotime($t->requested_at)) ?></td>
                            <td>
                                <a href="<?= base_url() ?>app/pharmacy/transfer_approve/<?= $t->transfer_id ?>" 
                                   class="btn btn-xs btn-success" onclick="return confirm('Approve this transfer? Stock will be deducted.')">
                                    <i class="fa fa-check"></i> Approve
                                </a>
                                <form method="POST" action="<?= base_url() ?>app/pharmacy/transfer_cancel/<?= $t->transfer_id ?>" style="display:inline;">
                                    <input type="hidden" name="reason" value="Cancelled by sender">
                                    <button type="submit" class="btn btn-xs btn-danger" onclick="return confirm('Cancel this transfer request?')">
                                        <i class="fa fa-times"></i> Cancel
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-filter"></i> Filter Transfer History</h3>
            </div>
            <div class="box-body">
                <form method="GET" class="form-inline">
                    <div class="form-group">
                        <select name="store_id" class="form-control">
                            <option value="">All Stores</option>
                            <?php foreach ($all_stores as $s): ?>
                            <option value="<?= $s->store_id ?>" <?= $filters['store_id'] == $s->store_id ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s->store_name) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <select name="status" class="form-control">
                            <option value="">All Status</option>
                            <option value="PENDING" <?= $filters['status'] === 'PENDING' ? 'selected' : '' ?>>Pending</option>
                            <option value="APPROVED" <?= $filters['status'] === 'APPROVED' ? 'selected' : '' ?>>Approved</option>
                            <option value="IN_TRANSIT" <?= $filters['status'] === 'IN_TRANSIT' ? 'selected' : '' ?>>In Transit</option>
                            <option value="RECEIVED" <?= $filters['status'] === 'RECEIVED' ? 'selected' : '' ?>>Received</option>
                            <option value="CANCELLED" <?= $filters['status'] === 'CANCELLED' ? 'selected' : '' ?>>Cancelled</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($filters['date_from']) ?>" placeholder="From">
                    </div>
                    <div class="form-group">
                        <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($filters['date_to']) ?>" placeholder="To">
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> Filter</button>
                    <a href="<?= base_url() ?>app/pharmacy/transfers" class="btn btn-default">Clear</a>
                </form>
            </div>
        </div>

        <!-- Transfer History -->
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-history"></i> Transfer History</h3>
            </div>
            <div class="box-body">
                <table class="table table-bordered table-striped table-hover" id="transfersTable">
                    <thead>
                        <tr>
                            <th>Transfer #</th>
                            <th>From</th>
                            <th>To</th>
                            <th>Drug</th>
                            <th>Qty</th>
                            <th>Status</th>
                            <th>Requested</th>
                            <th>Received</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($transfers)): foreach ($transfers as $t): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($t->transfer_no) ?></strong></td>
                            <td><?= htmlspecialchars($t->from_store_name) ?></td>
                            <td><?= htmlspecialchars($t->to_store_name) ?></td>
                            <td>
                                <?= htmlspecialchars($t->drug_name) ?>
                                <?php if ($t->batch_no): ?>
                                    <br><small class="text-muted">Batch: <?= htmlspecialchars($t->batch_no) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= number_format($t->quantity, 2) ?>
                                <?php if ($t->received_qty && $t->received_qty != $t->quantity): ?>
                                    <br><small class="text-warning">Received: <?= number_format($t->received_qty, 2) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $statusClass = array(
                                    'PENDING' => 'warning',
                                    'APPROVED' => 'info',
                                    'IN_TRANSIT' => 'primary',
                                    'RECEIVED' => 'success',
                                    'CANCELLED' => 'danger'
                                );
                                $cls = isset($statusClass[$t->status]) ? $statusClass[$t->status] : 'default';
                                ?>
                                <span class="label label-<?= $cls ?>"><?= $t->status ?></span>
                            </td>
                            <td>
                                <?= date('d M Y', strtotime($t->requested_at)) ?>
                                <br><small class="text-muted"><?= htmlspecialchars($t->requested_by) ?></small>
                            </td>
                            <td>
                                <?php if ($t->received_at): ?>
                                    <?= date('d M Y', strtotime($t->received_at)) ?>
                                    <br><small class="text-muted"><?= htmlspecialchars($t->received_by) ?></small>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Navigation -->
        <div class="row">
            <div class="col-md-6">
                <a href="<?= base_url() ?>app/pharmacy/stores" class="btn btn-block btn-default">
                    <i class="fa fa-building"></i> Back to Stores
                </a>
            </div>
            <div class="col-md-6">
                <a href="<?= base_url() ?>app/pharmacy" class="btn btn-block btn-default">
                    <i class="fa fa-medkit"></i> Pharmacy Worklist
                </a>
            </div>
        </div>
    </section>
    </aside>
</div>

<script src="<?php echo base_url(); ?>public/js/jquery.min.js"></script>
<script src="<?php echo base_url(); ?>public/js/bootstrap.min.js"></script>
<script src="<?php echo base_url(); ?>public/js/plugins/datatables/jquery.dataTables.js"></script>
<script src="<?php echo base_url(); ?>public/js/plugins/datatables/dataTables.bootstrap.js"></script>
<script src="<?php echo base_url(); ?>public/js/AdminLTE/app.js"></script>
<script>
$(function() {
    $('#transfersTable').DataTable({
        "order": [[6, "desc"]],
        "pageLength": 25
    });
});
</script>
</body>
</html>
