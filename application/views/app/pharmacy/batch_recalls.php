<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Batch Recalls | Hospital Management System</title>
    <meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>
    <link href="<?php echo base_url(); ?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/css/datatables/dataTables.bootstrap.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/plugins/select2/select2.min.css" rel="stylesheet" type="text/css" />
</head>
<body class="skin-blue">
<?php require_once(APPPATH.'views/include/header.php');?>

<div class="wrapper row-offcanvas row-offcanvas-left">
    <?php require_once(APPPATH.'views/include/sidebar.php');?>
    
    <aside class="right-side">
    <section class="content-header">
        <h1><i class="fa fa-exclamation-triangle"></i> Batch Recall Management</h1>
        <ol class="breadcrumb">
            <li><a href="<?= base_url() ?>app/pharmacy"><i class="fa fa-medkit"></i> Pharmacy</a></li>
            <li class="active">Batch Recalls</li>
        </ol>
    </section>

    <section class="content">
        <?= isset($message) ? $message : '' ?>

        <!-- Summary Cards -->
        <div class="row">
            <div class="col-md-4 col-sm-6">
                <div class="info-box bg-red">
                    <span class="info-box-icon"><i class="fa fa-exclamation-triangle"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Active Recalls</span>
                        <span class="info-box-number"><?= $active_count ?></span>
                    </div>
                </div>
            </div>
            <div class="col-md-4 col-sm-6">
                <div class="info-box bg-yellow">
                    <span class="info-box-icon"><i class="fa fa-bell"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Pending Notifications</span>
                        <span class="info-box-number"><?= $pending_notifications ?></span>
                    </div>
                </div>
            </div>
            <div class="col-md-4 col-sm-6">
                <div class="info-box bg-aqua">
                    <span class="info-box-icon"><i class="fa fa-list"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Total Recalls</span>
                        <span class="info-box-number"><?= count($recalls) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-filter"></i> Filters</h3>
                <div class="box-tools pull-right">
                    <button type="button" class="btn btn-danger btn-sm" data-toggle="modal" data-target="#createRecallModal">
                        <i class="fa fa-plus"></i> Create Recall
                    </button>
                </div>
            </div>
            <div class="box-body">
                <form method="GET" class="form-inline">
                    <div class="form-group">
                        <select name="status" class="form-control">
                            <option value="">All Status</option>
                            <option value="ACTIVE" <?= $filters['status'] === 'ACTIVE' ? 'selected' : '' ?>>Active</option>
                            <option value="RESOLVED" <?= $filters['status'] === 'RESOLVED' ? 'selected' : '' ?>>Resolved</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <input type="date" name="date_from" class="form-control" placeholder="From" value="<?= htmlspecialchars($filters['date_from']) ?>">
                    </div>
                    <div class="form-group">
                        <input type="date" name="date_to" class="form-control" placeholder="To" value="<?= htmlspecialchars($filters['date_to']) ?>">
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> Filter</button>
                    <a href="<?= base_url() ?>app/pharmacy/batch_recalls" class="btn btn-default">Clear</a>
                </form>
            </div>
        </div>

        <!-- Recalls Table -->
        <div class="box box-danger">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-list"></i> Batch Recalls (<?= count($recalls) ?>)</h3>
            </div>
            <div class="box-body">
                <?php if (!empty($recalls)): ?>
                <table class="table table-bordered table-striped" id="recallsTable">
                    <thead>
                        <tr>
                            <th>Drug / Batch</th>
                            <th>Type / Class</th>
                            <th>Recall Date</th>
                            <th>Affected</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recalls as $r): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($r->drug_name) ?></strong>
                                <br><span class="label label-default"><?= htmlspecialchars($r->batch_number) ?></span>
                            </td>
                            <td>
                                <?= isset($recall_types[$r->recall_type]) ? $recall_types[$r->recall_type] : $r->recall_type ?>
                                <?php if ($r->recall_class): ?>
                                    <br><span class="label label-<?= $r->recall_class === 'I' ? 'danger' : ($r->recall_class === 'II' ? 'warning' : 'info') ?>">
                                        Class <?= $r->recall_class ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= date('d M Y', strtotime($r->recall_date)) ?>
                                <br><small class="text-muted">Eff: <?= date('d M Y', strtotime($r->effective_date)) ?></small>
                            </td>
                            <td>
                                <i class="fa fa-cubes"></i> <?= number_format($r->affected_qty_in_stock) ?> in stock
                                <br><i class="fa fa-users"></i> <?= $r->affected_patients ?> patients
                                <br><i class="fa fa-check"></i> <?= $r->patients_notified ?> notified
                            </td>
                            <td>
                                <?php if ($r->status === 'ACTIVE'): ?>
                                    <span class="label label-danger"><i class="fa fa-exclamation-triangle"></i> Active</span>
                                <?php else: ?>
                                    <span class="label label-success"><i class="fa fa-check"></i> Resolved</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?= base_url() ?>app/pharmacy/recall_details/<?= $r->recall_id ?>" class="btn btn-xs btn-primary">
                                    <i class="fa fa-eye"></i> Details
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="alert alert-success">
                    <i class="fa fa-check"></i> No batch recalls found.
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Navigation -->
        <div class="row">
            <div class="col-md-6">
                <a href="<?= base_url() ?>app/pharmacy" class="btn btn-block btn-default">
                    <i class="fa fa-arrow-left"></i> Back to Worklist
                </a>
            </div>
            <div class="col-md-6">
                <a href="<?= base_url() ?>app/pharmacy/alerts" class="btn btn-block btn-warning">
                    <i class="fa fa-bell"></i> Pharmacy Alerts
                </a>
            </div>
        </div>
    </section>
</div>

<!-- Create Recall Modal -->
<div class="modal fade" id="createRecallModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="<?= base_url() ?>app/pharmacy/create_recall">
            <div class="modal-content">
                <div class="modal-header bg-red">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title"><i class="fa fa-exclamation-triangle"></i> Create Batch Recall</h4>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Drug <span class="text-red">*</span></label>
                                <select name="drug_id" class="form-control" required>
                                    <option value="">-- Select Drug --</option>
                                </select>
                                <small class="text-muted">Start typing to search drugs</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Batch Number <span class="text-red">*</span></label>
                                <input type="text" name="batch_number" class="form-control" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Recall Type <span class="text-red">*</span></label>
                                <select name="recall_type" class="form-control" required>
                                    <?php foreach ($recall_types as $code => $label): ?>
                                    <option value="<?= $code ?>"><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Recall Class</label>
                                <select name="recall_class" class="form-control">
                                    <option value="">-- Select --</option>
                                    <?php foreach ($recall_classes as $code => $label): ?>
                                    <option value="<?= $code ?>"><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Manufacturer</label>
                                <input type="text" name="manufacturer" class="form-control">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Recall Date <span class="text-red">*</span></label>
                                <input type="date" name="recall_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Effective Date <span class="text-red">*</span></label>
                                <input type="date" name="effective_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Recall Reason <span class="text-red">*</span></label>
                        <textarea name="recall_reason" class="form-control" rows="3" required placeholder="Describe the reason for the recall..."></textarea>
                    </div>
                    <div class="form-group">
                        <label>Regulatory Reference</label>
                        <input type="text" name="regulatory_reference" class="form-control" placeholder="e.g. FDA-GH-2024-001">
                    </div>
                    <div class="form-group">
                        <label>Instructions for Patients/Staff</label>
                        <textarea name="instructions" class="form-control" rows="2" placeholder="What should patients do if they have this medication?"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger"><i class="fa fa-exclamation-triangle"></i> Create Recall</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
$(function() {
    $('#recallsTable').DataTable({
        "order": [[2, "desc"]],
        "pageLength": 25
    });

    // Drug search autocomplete
    $('select[name="drug_id"]').select2({
        ajax: {
            url: '<?= base_url() ?>app/pharmacy/drug_search_json',
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return { term: params.term };
            },
            processResults: function(data) {
                return {
                    results: data.map(function(item) {
                        return { id: item.drug_id, text: item.drug_name };
                    })
                };
            }
        },
        minimumInputLength: 2,
        placeholder: 'Search for a drug...'
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
<script src="<?php echo base_url(); ?>public/js/plugins/select2/select2.full.min.js"></script>
<script src="<?php echo base_url(); ?>public/dist/js/app.min.js"></script>
</body>
</html>
