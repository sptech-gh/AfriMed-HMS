<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Pharmacy Stores | Hospital Management System</title>
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
        <h1><i class="fa fa-building"></i> Pharmacy Stores Management</h1>
        <ol class="breadcrumb">
            <li><a href="<?= base_url() ?>app/pharmacy"><i class="fa fa-medkit"></i> Pharmacy</a></li>
            <li class="active">Stores</li>
        </ol>
    </section>

    <section class="content">
        <?= isset($message) ? $message : '' ?>

        <!-- Summary Cards -->
        <div class="row">
            <?php foreach ($summary as $s): ?>
            <div class="col-md-3 col-sm-6">
                <div class="info-box <?= $s->store_type === 'MAIN' ? 'bg-green' : 'bg-aqua' ?>">
                    <span class="info-box-icon"><i class="fa fa-building"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text"><?= htmlspecialchars($s->store_name) ?></span>
                        <span class="info-box-number"><?= number_format($s->total_items) ?> items</span>
                        <div class="progress"><div class="progress-bar" style="width: 100%"></div></div>
                        <span class="progress-description">
                            <?php if ($s->low_stock_count > 0): ?>
                                <span class="text-yellow"><i class="fa fa-warning"></i> <?= $s->low_stock_count ?> low</span>
                            <?php endif; ?>
                            <?php if ($s->expiring_count > 0): ?>
                                <span class="text-red"><i class="fa fa-clock-o"></i> <?= $s->expiring_count ?> expiring</span>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            
            <div class="col-md-3 col-sm-6">
                <div class="info-box bg-yellow">
                    <span class="info-box-icon"><i class="fa fa-exchange"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Pending Transfers</span>
                        <span class="info-box-number"><?= $pending_transfers ?></span>
                        <a href="<?= base_url() ?>app/pharmacy/transfers" class="small-box-footer">
                            View Transfers <i class="fa fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stores List -->
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-list"></i> All Pharmacy Stores</h3>
                <div class="box-tools pull-right">
                    <?php if ($this->session->userdata('role') === 'admin'): ?>
                    <button type="button" class="btn btn-success btn-sm" data-toggle="modal" data-target="#addStoreModal">
                        <i class="fa fa-plus"></i> Add Store
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="box-body">
                <table class="table table-bordered table-striped table-hover" id="storesTable">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Store Name</th>
                            <th>Type</th>
                            <th>Location</th>
                            <th>Status</th>
                            <th>Items</th>
                            <th>Stock Value</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stores as $store): 
                            $summ = null;
                            foreach ($summary as $s) {
                                if ($s->store_id == $store->store_id) { $summ = $s; break; }
                            }
                        ?>
                        <tr class="<?= !$store->is_active ? 'text-muted' : '' ?>">
                            <td><strong><?= htmlspecialchars($store->store_code) ?></strong></td>
                            <td><?= htmlspecialchars($store->store_name) ?></td>
                            <td>
                                <?php
                                $typeClass = array('MAIN' => 'success', 'SATELLITE' => 'info', 'WARD' => 'warning', 'EMERGENCY' => 'danger');
                                $cls = isset($typeClass[$store->store_type]) ? $typeClass[$store->store_type] : 'default';
                                ?>
                                <span class="label label-<?= $cls ?>"><?= $store->store_type ?></span>
                            </td>
                            <td><?= htmlspecialchars($store->location ?: '-') ?></td>
                            <td>
                                <?php if ($store->is_active): ?>
                                    <span class="label label-success">Active</span>
                                <?php else: ?>
                                    <span class="label label-default">Inactive</span>
                                <?php endif; ?>
                                <?php if ($store->can_dispense): ?>
                                    <span class="label label-info" title="Can Dispense"><i class="fa fa-check"></i> Dispense</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $summ ? number_format($summ->total_items) : 0 ?></td>
                            <td>GHS <?= $summ ? number_format($summ->total_value, 2) : '0.00' ?></td>
                            <td>
                                <a href="<?= base_url() ?>app/pharmacy/store_stock/<?= $store->store_id ?>" class="btn btn-xs btn-primary" title="View Stock">
                                    <i class="fa fa-cubes"></i>
                                </a>
                                <?php if ($this->session->userdata('role') === 'admin'): ?>
                                <button type="button" class="btn btn-xs btn-warning btn-edit-store" 
                                        data-store='<?= json_encode($store) ?>' title="Edit">
                                    <i class="fa fa-edit"></i>
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Quick Links -->
        <div class="row">
            <div class="col-md-4">
                <a href="<?= base_url() ?>app/pharmacy/transfers" class="btn btn-block btn-default">
                    <i class="fa fa-exchange"></i> Stock Transfers
                </a>
            </div>
            <div class="col-md-4">
                <a href="<?= base_url() ?>app/pharmacy/low_stock_report" class="btn btn-block btn-default">
                    <i class="fa fa-warning"></i> Low Stock Report
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

<!-- Add Store Modal -->
<div class="modal fade" id="addStoreModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="<?= base_url() ?>app/pharmacy/store_add">
            <div class="modal-content">
                <div class="modal-header bg-green">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title"><i class="fa fa-plus"></i> Add New Pharmacy Store</h4>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Store Code <span class="text-red">*</span></label>
                                <input type="text" name="store_code" class="form-control" required maxlength="20" placeholder="e.g. WARD-A">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Store Type <span class="text-red">*</span></label>
                                <select name="store_type" class="form-control" required>
                                    <option value="SATELLITE">Satellite Pharmacy</option>
                                    <option value="WARD">Ward Pharmacy</option>
                                    <option value="EMERGENCY">Emergency Pharmacy</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Store Name <span class="text-red">*</span></label>
                        <input type="text" name="store_name" class="form-control" required maxlength="100">
                    </div>
                    <div class="form-group">
                        <label>Location</label>
                        <input type="text" name="location" class="form-control" maxlength="255">
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Contact Phone</label>
                                <input type="text" name="contact_phone" class="form-control" maxlength="20">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Operating Hours</label>
                                <input type="text" name="operating_hours" class="form-control" placeholder="e.g. 08:00-20:00">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="checkbox">
                                <label><input type="checkbox" name="can_dispense" value="1" checked> Can Dispense Medications</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="checkbox">
                                <label><input type="checkbox" name="can_receive_transfers" value="1" checked> Can Receive Transfers</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="fa fa-save"></i> Add Store</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Edit Store Modal -->
<div class="modal fade" id="editStoreModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" id="editStoreForm">
            <div class="modal-content">
                <div class="modal-header bg-yellow">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title"><i class="fa fa-edit"></i> Edit Pharmacy Store</h4>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Store Code</label>
                                <input type="text" id="edit_store_code" class="form-control" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Store Type <span class="text-red">*</span></label>
                                <select name="store_type" id="edit_store_type" class="form-control" required>
                                    <option value="MAIN">Main Pharmacy</option>
                                    <option value="SATELLITE">Satellite Pharmacy</option>
                                    <option value="WARD">Ward Pharmacy</option>
                                    <option value="EMERGENCY">Emergency Pharmacy</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Store Name <span class="text-red">*</span></label>
                        <input type="text" name="store_name" id="edit_store_name" class="form-control" required maxlength="100">
                    </div>
                    <div class="form-group">
                        <label>Location</label>
                        <input type="text" name="location" id="edit_location" class="form-control" maxlength="255">
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Contact Phone</label>
                                <input type="text" name="contact_phone" id="edit_contact_phone" class="form-control" maxlength="20">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Operating Hours</label>
                                <input type="text" name="operating_hours" id="edit_operating_hours" class="form-control">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="checkbox">
                                <label><input type="checkbox" name="is_active" id="edit_is_active" value="1"> Active</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="checkbox">
                                <label><input type="checkbox" name="can_dispense" id="edit_can_dispense" value="1"> Can Dispense</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="checkbox">
                                <label><input type="checkbox" name="can_receive_transfers" id="edit_can_receive_transfers" value="1"> Can Receive</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning"><i class="fa fa-save"></i> Update Store</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
$(function() {
    $('#storesTable').DataTable({
        "order": [[2, "asc"], [1, "asc"]],
        "pageLength": 25
    });

    $('.btn-edit-store').click(function() {
        var store = $(this).data('store');
        $('#editStoreForm').attr('action', '<?= base_url() ?>app/pharmacy/store_edit/' + store.store_id);
        $('#edit_store_code').val(store.store_code);
        $('#edit_store_type').val(store.store_type);
        $('#edit_store_name').val(store.store_name);
        $('#edit_location').val(store.location || '');
        $('#edit_contact_phone').val(store.contact_phone || '');
        $('#edit_operating_hours').val(store.operating_hours || '');
        $('#edit_is_active').prop('checked', store.is_active == 1);
        $('#edit_can_dispense').prop('checked', store.can_dispense == 1);
        $('#edit_can_receive_transfers').prop('checked', store.can_receive_transfers == 1);
        $('#editStoreModal').modal('show');
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
