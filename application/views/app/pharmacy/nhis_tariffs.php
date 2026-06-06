<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>NHIS Tariffs | Hospital Management System</title>
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
        <h1><i class="fa fa-list-alt"></i> NHIS Drug Tariffs</h1>
        <ol class="breadcrumb">
            <li><a href="<?= base_url() ?>app/pharmacy"><i class="fa fa-medkit"></i> Pharmacy</a></li>
            <li><a href="<?= base_url() ?>app/pharmacy/nhis_drug_mapping">NHIS Mapping</a></li>
            <li class="active">Tariffs</li>
        </ol>
    </section>

    <section class="content">
        <?= isset($message) ? $message : '' ?>

        <!-- Filters -->
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-filter"></i> Filters</h3>
            </div>
            <div class="box-body">
                <form method="GET" class="form-inline">
                    <div class="form-group">
                        <select name="category" class="form-control">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat->category) ?>" <?= $filters['category'] === $cat->category ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat->category) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <input type="text" name="search" class="form-control" placeholder="Search..." value="<?= htmlspecialchars($filters['search']) ?>">
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> Filter</button>
                    <a href="<?= base_url() ?>app/pharmacy/nhis_tariffs" class="btn btn-default">Clear</a>
                </form>
            </div>
        </div>

        <!-- Tariffs Table -->
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-list"></i> NHIS Drug Tariffs (<?= count($tariffs) ?>)</h3>
            </div>
            <div class="box-body">
                <?php if (!empty($tariffs)): ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="tariffsTable">
                        <thead>
                            <tr>
                                <th>NHIS Code</th>
                                <th>Drug Name</th>
                                <th>Generic Name</th>
                                <th>Form / Strength</th>
                                <th>Unit Price</th>
                                <th>Category</th>
                                <th>Flags</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tariffs as $t): ?>
                            <tr>
                                <td><span class="label label-primary"><?= htmlspecialchars($t->nhis_code) ?></span></td>
                                <td><strong><?= htmlspecialchars($t->drug_name) ?></strong></td>
                                <td><?= htmlspecialchars($t->generic_name ?: '-') ?></td>
                                <td>
                                    <?= htmlspecialchars($t->dosage_form ?: '-') ?>
                                    <?php if ($t->strength): ?>
                                        <br><small><?= htmlspecialchars($t->strength) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><strong>GHS <?= number_format($t->unit_price, 2) ?></strong></td>
                                <td><?= htmlspecialchars($t->category ?: '-') ?></td>
                                <td>
                                    <?php if ($t->is_essential): ?>
                                        <span class="label label-success">Essential</span>
                                    <?php endif; ?>
                                    <?php if ($t->requires_authorization): ?>
                                        <span class="label label-warning">Auth Required</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="alert alert-info">
                    <i class="fa fa-info-circle"></i> No tariffs found matching your criteria.
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Navigation -->
        <div class="row">
            <div class="col-md-6">
                <a href="<?= base_url() ?>app/pharmacy/nhis_drug_mapping" class="btn btn-block btn-default">
                    <i class="fa fa-arrow-left"></i> Back to Drug Mapping
                </a>
            </div>
            <div class="col-md-6">
                <a href="<?= base_url() ?>app/pharmacy" class="btn btn-block btn-primary">
                    <i class="fa fa-medkit"></i> Pharmacy Worklist
                </a>
            </div>
        </div>
    </section>
</div>

<script>
$(function() {
    $('#tariffsTable').DataTable({
        "pageLength": 50,
        "order": [[1, "asc"]]
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
