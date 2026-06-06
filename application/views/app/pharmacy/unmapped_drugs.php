<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Unmapped Drugs | Hospital Management System</title>
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
        <h1><i class="fa fa-unlink"></i> Unmapped Brand Drugs</h1>
        <ol class="breadcrumb">
            <li><a href="<?= base_url() ?>app/pharmacy"><i class="fa fa-medkit"></i> Pharmacy</a></li>
            <li><a href="<?= base_url() ?>app/pharmacy/generic_drugs">Generic Drugs</a></li>
            <li class="active">Unmapped Brands</li>
        </ol>
    </section>

    <section class="content">
        <?= isset($message) ? $message : '' ?>

        <div class="callout callout-warning">
            <h4><i class="fa fa-warning"></i> Unmapped Drugs</h4>
            <p>These brand drugs have not been mapped to any generic drug. Mapping allows for generic substitution suggestions and better inventory management.</p>
        </div>

        <!-- Unmapped Drugs Table -->
        <div class="box box-warning">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-list"></i> Unmapped Brand Drugs (<?= count($drugs) ?>)</h3>
            </div>
            <div class="box-body">
                <?php if (!empty($drugs)): ?>
                <table class="table table-bordered table-striped table-hover" id="unmappedTable">
                    <thead>
                        <tr>
                            <th>Drug Name</th>
                            <th>Form / Strength</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($drugs as $d): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($d->drug_name) ?></strong>
                                <?php if (isset($d->generic_name) && $d->generic_name): ?>
                                    <br><small class="text-muted"><?= htmlspecialchars($d->generic_name) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= isset($d->dosage_form) ? htmlspecialchars($d->dosage_form) : '-' ?>
                                <?php if (isset($d->strength) && $d->strength): ?>
                                    / <?= htmlspecialchars($d->strength) ?>
                                <?php endif; ?>
                            </td>
                            <td><?= isset($d->category) ? htmlspecialchars($d->category) : '-' ?></td>
                            <td class="text-right">GHS <?= number_format($d->nPrice, 2) ?></td>
                            <td class="text-center">
                                <?php if ($d->nStock > 0): ?>
                                    <span class="label label-success"><?= number_format($d->nStock) ?></span>
                                <?php else: ?>
                                    <span class="label label-danger">Out</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button type="button" class="btn btn-xs btn-success btn-quick-map"
                                        data-drug-id="<?= $d->drug_id ?>"
                                        data-drug-name="<?= htmlspecialchars($d->drug_name) ?>">
                                    <i class="fa fa-link"></i> Map
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="alert alert-success">
                    <i class="fa fa-check"></i> All drugs have been mapped to their generics!
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Navigation -->
        <div class="row">
            <div class="col-md-6">
                <a href="<?= base_url() ?>app/pharmacy/generic_drugs" class="btn btn-block btn-default">
                    <i class="fa fa-arrow-left"></i> Back to Generic Drugs
                </a>
            </div>
            <div class="col-md-6">
                <a href="<?= base_url() ?>app/pharmacy" class="btn btn-block btn-default">
                    <i class="fa fa-medkit"></i> Pharmacy Worklist
                </a>
            </div>
        </div>
    </section>
</div>

<!-- Quick Map Modal -->
<div class="modal fade" id="quickMapModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="<?= base_url() ?>app/pharmacy/map_brand">
            <input type="hidden" name="drug_id" id="map_drug_id">
            <div class="modal-content">
                <div class="modal-header bg-green">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title"><i class="fa fa-link"></i> Map <span id="map_drug_name"></span></h4>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Select Generic Drug <span class="text-red">*</span></label>
                        <select name="generic_id" id="map_generic_id" class="form-control" required>
                            <option value="">-- Select Generic --</option>
                            <?php foreach ($generics as $g): ?>
                            <option value="<?= $g->generic_id ?>">
                                <?= htmlspecialchars($g->generic_name) ?>
                                <?php if ($g->therapeutic_class): ?>
                                    (<?= htmlspecialchars($g->therapeutic_class) ?>)
                                <?php endif; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Manufacturer</label>
                                <input type="text" name="manufacturer" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Country of Origin</label>
                                <input type="text" name="country_of_origin" class="form-control">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Bioequivalence Rating</label>
                        <select name="bioequivalence_rating" class="form-control">
                            <option value="">-- Select --</option>
                            <option value="AA">AA - Therapeutic Equivalent</option>
                            <option value="AB">AB - Bioequivalent</option>
                            <option value="BC">BC - Extended Release</option>
                            <option value="BD">BD - Documented Bioequivalence</option>
                            <option value="BX">BX - Insufficient Data</option>
                        </select>
                    </div>
                    <div class="checkbox">
                        <label><input type="checkbox" name="is_primary_brand" value="1"> Set as Primary Brand</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="fa fa-link"></i> Map to Generic</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
$(function() {
    $('#unmappedTable').DataTable({
        "order": [[0, "asc"]],
        "pageLength": 50
    });

    $('.btn-quick-map').click(function() {
        var drugId = $(this).data('drug-id');
        var drugName = $(this).data('drug-name');
        
        $('#map_drug_id').val(drugId);
        $('#map_drug_name').text(drugName);
        $('#map_generic_id').val('');
        $('#quickMapModal').modal('show');
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
