<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Generic Drugs | Hospital Management System</title>
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
        <h1><i class="fa fa-flask"></i> Generic Drug Master</h1>
        <ol class="breadcrumb">
            <li><a href="<?= base_url() ?>app/pharmacy"><i class="fa fa-medkit"></i> Pharmacy</a></li>
            <li class="active">Generic Drugs</li>
        </ol>
    </section>

    <section class="content">
        <?= isset($message) ? $message : '' ?>

        <!-- Summary Cards -->
        <div class="row">
            <div class="col-md-3 col-sm-6">
                <div class="info-box bg-aqua">
                    <span class="info-box-icon"><i class="fa fa-flask"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Generic Drugs</span>
                        <span class="info-box-number"><?= count($generics) ?></span>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <a href="<?= base_url() ?>app/pharmacy/unmapped_drugs" class="info-box bg-yellow">
                    <span class="info-box-icon"><i class="fa fa-unlink"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Unmapped Brands</span>
                        <span class="info-box-number"><?= $unmapped_count ?></span>
                    </div>
                </a>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="info-box bg-green">
                    <span class="info-box-icon"><i class="fa fa-star"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Essential Drugs</span>
                        <span class="info-box-number">
                            <?php 
                            $essential = 0;
                            foreach ($generics as $g) { if ($g->is_essential) $essential++; }
                            echo $essential;
                            ?>
                        </span>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="info-box bg-purple">
                    <span class="info-box-icon"><i class="fa fa-hospital-o"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">NHIS Listed</span>
                        <span class="info-box-number">
                            <?php 
                            $nhis = 0;
                            foreach ($generics as $g) { if ($g->is_nhis_listed) $nhis++; }
                            echo $nhis;
                            ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-filter"></i> Filters</h3>
                <div class="box-tools pull-right">
                    <button type="button" class="btn btn-success btn-sm" data-toggle="modal" data-target="#addGenericModal">
                        <i class="fa fa-plus"></i> Add Generic Drug
                    </button>
                </div>
            </div>
            <div class="box-body">
                <form method="GET" class="form-inline">
                    <div class="form-group">
                        <input type="text" name="search" class="form-control" placeholder="Search..." value="<?= htmlspecialchars($filters['search']) ?>">
                    </div>
                    <div class="form-group">
                        <select name="therapeutic_class" class="form-control">
                            <option value="">All Classes</option>
                            <?php foreach ($therapeutic_classes as $tc): ?>
                            <option value="<?= htmlspecialchars($tc) ?>" <?= $filters['therapeutic_class'] === $tc ? 'selected' : '' ?>>
                                <?= htmlspecialchars($tc) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <select name="is_essential" class="form-control">
                            <option value="">All</option>
                            <option value="1" <?= $filters['is_essential'] === '1' ? 'selected' : '' ?>>Essential Only</option>
                            <option value="0" <?= $filters['is_essential'] === '0' ? 'selected' : '' ?>>Non-Essential</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <select name="is_nhis_listed" class="form-control">
                            <option value="">All</option>
                            <option value="1" <?= $filters['is_nhis_listed'] === '1' ? 'selected' : '' ?>>NHIS Listed</option>
                            <option value="0" <?= $filters['is_nhis_listed'] === '0' ? 'selected' : '' ?>>Not NHIS</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> Filter</button>
                    <a href="<?= base_url() ?>app/pharmacy/generic_drugs" class="btn btn-default">Clear</a>
                </form>
            </div>
        </div>

        <!-- Generic Drugs Table -->
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-list"></i> Generic Drugs (<?= count($generics) ?>)</h3>
            </div>
            <div class="box-body">
                <table class="table table-bordered table-striped table-hover" id="genericsTable">
                    <thead>
                        <tr>
                            <th>Generic Name</th>
                            <th>Therapeutic Class</th>
                            <th>ATC Code</th>
                            <th>Brands</th>
                            <th>Flags</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($generics as $g): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($g->generic_name) ?></strong>
                                <?php if ($g->generic_code): ?>
                                    <br><small class="text-muted"><?= htmlspecialchars($g->generic_code) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($g->therapeutic_class ?: '-') ?></td>
                            <td><?= htmlspecialchars($g->atc_code ?: '-') ?></td>
                            <td class="text-center">
                                <a href="<?= base_url() ?>app/pharmacy/generic_brands/<?= $g->generic_id ?>" class="btn btn-xs btn-info">
                                    <i class="fa fa-tags"></i> <?= $g->brand_count ?> brands
                                </a>
                            </td>
                            <td>
                                <?php if ($g->is_essential): ?>
                                    <span class="label label-success"><i class="fa fa-star"></i> Essential</span>
                                <?php endif; ?>
                                <?php if ($g->is_nhis_listed): ?>
                                    <span class="label label-primary"><i class="fa fa-hospital-o"></i> NHIS</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button type="button" class="btn btn-xs btn-warning btn-edit-generic"
                                        data-generic='<?= json_encode($g) ?>'>
                                    <i class="fa fa-edit"></i>
                                </button>
                                <a href="<?= base_url() ?>app/pharmacy/generic_brands/<?= $g->generic_id ?>" class="btn btn-xs btn-primary">
                                    <i class="fa fa-link"></i> Map Brands
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($generics)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted">No generic drugs found.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Navigation -->
        <div class="row">
            <div class="col-md-6">
                <a href="<?= base_url() ?>app/pharmacy/unmapped_drugs" class="btn btn-block btn-warning">
                    <i class="fa fa-unlink"></i> View Unmapped Brands (<?= $unmapped_count ?>)
                </a>
            </div>
            <div class="col-md-6">
                <a href="<?= base_url() ?>app/pharmacy" class="btn btn-block btn-default">
                    <i class="fa fa-arrow-left"></i> Back to Worklist
                </a>
            </div>
        </div>
    </section>
</div>

<!-- Add Generic Modal -->
<div class="modal fade" id="addGenericModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="<?= base_url() ?>app/pharmacy/add_generic">
            <div class="modal-content">
                <div class="modal-header bg-green">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title"><i class="fa fa-plus"></i> Add Generic Drug</h4>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="form-group">
                                <label>Generic Name <span class="text-red">*</span></label>
                                <input type="text" name="generic_name" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Generic Code</label>
                                <input type="text" name="generic_code" class="form-control" placeholder="e.g. GEN001">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Therapeutic Class</label>
                                <input type="text" name="therapeutic_class" class="form-control" placeholder="e.g. Analgesics">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Pharmacological Class</label>
                                <input type="text" name="pharmacological_class" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>ATC Code</label>
                                <input type="text" name="atc_code" class="form-control" placeholder="e.g. N02BE01">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Common Dosage Forms</label>
                                <input type="text" name="common_dosage_forms" class="form-control" placeholder="e.g. Tablet, Capsule, Syrup">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Common Strengths</label>
                                <input type="text" name="common_strengths" class="form-control" placeholder="e.g. 500mg, 250mg">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="checkbox">
                                <label><input type="checkbox" name="is_essential" value="1"> Essential Drug (EML)</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="checkbox">
                                <label><input type="checkbox" name="is_nhis_listed" value="1"> NHIS Listed</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="fa fa-save"></i> Add Generic</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Edit Generic Modal -->
<div class="modal fade" id="editGenericModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" id="editGenericForm">
            <div class="modal-content">
                <div class="modal-header bg-yellow">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title"><i class="fa fa-edit"></i> Edit Generic Drug</h4>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="form-group">
                                <label>Generic Name <span class="text-red">*</span></label>
                                <input type="text" name="generic_name" id="edit_generic_name" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Generic Code</label>
                                <input type="text" name="generic_code" id="edit_generic_code" class="form-control">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Therapeutic Class</label>
                                <input type="text" name="therapeutic_class" id="edit_therapeutic_class" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Pharmacological Class</label>
                                <input type="text" name="pharmacological_class" id="edit_pharmacological_class" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>ATC Code</label>
                                <input type="text" name="atc_code" id="edit_atc_code" class="form-control">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Common Dosage Forms</label>
                                <input type="text" name="common_dosage_forms" id="edit_common_dosage_forms" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Common Strengths</label>
                                <input type="text" name="common_strengths" id="edit_common_strengths" class="form-control">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" id="edit_description" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="checkbox">
                                <label><input type="checkbox" name="is_essential" id="edit_is_essential" value="1"> Essential Drug (EML)</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="checkbox">
                                <label><input type="checkbox" name="is_nhis_listed" id="edit_is_nhis_listed" value="1"> NHIS Listed</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning"><i class="fa fa-save"></i> Update</button>
                </div>
            </div>
        </form>
    </div>
</div>

    </aside>
</div>

<script src="<?php echo base_url(); ?>public/js/jquery.min.js"></script>
<script src="<?php echo base_url(); ?>public/js/bootstrap.min.js"></script>
<script src="<?php echo base_url(); ?>public/js/plugins/datatables/jquery.dataTables.js"></script>
<script src="<?php echo base_url(); ?>public/js/plugins/datatables/dataTables.bootstrap.js"></script>
<script src="<?php echo base_url(); ?>public/js/AdminLTE/app.js"></script>
<script>
$(function() {
    $('#genericsTable').DataTable({
        "order": [[0, "asc"]],
        "pageLength": 25
    });

    $('.btn-edit-generic').click(function() {
        var g = $(this).data('generic');
        $('#editGenericForm').attr('action', '<?= base_url() ?>app/pharmacy/edit_generic/' + g.generic_id);
        $('#edit_generic_name').val(g.generic_name);
        $('#edit_generic_code').val(g.generic_code || '');
        $('#edit_therapeutic_class').val(g.therapeutic_class || '');
        $('#edit_pharmacological_class').val(g.pharmacological_class || '');
        $('#edit_atc_code').val(g.atc_code || '');
        $('#edit_common_dosage_forms').val(g.common_dosage_forms || '');
        $('#edit_common_strengths').val(g.common_strengths || '');
        $('#edit_description').val(g.description || '');
        $('#edit_is_essential').prop('checked', g.is_essential == 1);
        $('#edit_is_nhis_listed').prop('checked', g.is_nhis_listed == 1);
        $('#editGenericModal').modal('show');
    });
});
</script>
</body>
</html>
