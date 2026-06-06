<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Generic Brands | Hospital Management System</title>
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
        <h1><i class="fa fa-tags"></i> Brand Mapping: <?= htmlspecialchars($generic->generic_name) ?></h1>
        <ol class="breadcrumb">
            <li><a href="<?= base_url() ?>app/pharmacy"><i class="fa fa-medkit"></i> Pharmacy</a></li>
            <li><a href="<?= base_url() ?>app/pharmacy/generic_drugs">Generic Drugs</a></li>
            <li class="active"><?= htmlspecialchars($generic->generic_name) ?></li>
        </ol>
    </section>

    <section class="content">
        <?= isset($message) ? $message : '' ?>

        <!-- Generic Info -->
        <div class="callout callout-info">
            <div class="row">
                <div class="col-md-4">
                    <strong>Generic Name:</strong> <?= htmlspecialchars($generic->generic_name) ?>
                    <?php if ($generic->is_essential): ?>
                        <span class="label label-success">Essential</span>
                    <?php endif; ?>
                    <?php if ($generic->is_nhis_listed): ?>
                        <span class="label label-primary">NHIS</span>
                    <?php endif; ?>
                </div>
                <div class="col-md-3">
                    <strong>Therapeutic Class:</strong> <?= htmlspecialchars($generic->therapeutic_class ?: 'N/A') ?>
                </div>
                <div class="col-md-2">
                    <strong>ATC Code:</strong> <?= htmlspecialchars($generic->atc_code ?: 'N/A') ?>
                </div>
                <div class="col-md-3">
                    <strong>Mapped Brands:</strong> <?= count($brands) ?>
                </div>
            </div>
            <?php if ($generic->description): ?>
            <hr style="margin: 10px 0;">
            <small><?= htmlspecialchars($generic->description) ?></small>
            <?php endif; ?>
        </div>

        <!-- Mapped Brands -->
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-link"></i> Mapped Brand Drugs (<?= count($brands) ?>)</h3>
                <div class="box-tools pull-right">
                    <button type="button" class="btn btn-success btn-sm" data-toggle="modal" data-target="#mapBrandModal">
                        <i class="fa fa-plus"></i> Map Brand
                    </button>
                </div>
            </div>
            <div class="box-body">
                <?php if (!empty($brands)): ?>
                <table class="table table-bordered table-striped" id="brandsTable">
                    <thead>
                        <tr>
                            <th>Brand Name</th>
                            <th>Form / Strength</th>
                            <th>Manufacturer</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Rating</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($brands as $b): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($b->drug_name) ?></strong>
                                <?php if ($b->is_primary_brand): ?>
                                    <span class="label label-success">Primary</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($b->dosage_form ?: '-') ?>
                                <?php if ($b->strength): ?>
                                    / <?= htmlspecialchars($b->strength) ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($b->manufacturer ?: '-') ?>
                                <?php if ($b->country_of_origin): ?>
                                    <br><small class="text-muted"><?= htmlspecialchars($b->country_of_origin) ?></small>
                                <?php endif; ?>
                            </td>
                            <td class="text-right">GHS <?= number_format($b->nPrice, 2) ?></td>
                            <td class="text-center">
                                <?php if ($b->nStock > 0): ?>
                                    <span class="label label-success"><?= number_format($b->nStock) ?></span>
                                <?php else: ?>
                                    <span class="label label-danger">Out</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($b->bioequivalence_rating): ?>
                                    <span class="label label-info"><?= htmlspecialchars($b->bioequivalence_rating) ?></span>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?= base_url() ?>app/pharmacy/unmap_brand/<?= $b->mapping_id ?>?generic_id=<?= $generic->generic_id ?>" 
                                   class="btn btn-xs btn-danger" onclick="return confirm('Remove this brand mapping?')">
                                    <i class="fa fa-unlink"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="alert alert-info">
                    <i class="fa fa-info-circle"></i> No brands mapped to this generic yet. Click "Map Brand" to add one.
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
                <a href="<?= base_url() ?>app/pharmacy/unmapped_drugs" class="btn btn-block btn-warning">
                    <i class="fa fa-unlink"></i> View Unmapped Brands
                </a>
            </div>
        </div>
    </section>
</div>

<!-- Map Brand Modal -->
<div class="modal fade" id="mapBrandModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="<?= base_url() ?>app/pharmacy/map_brand">
            <input type="hidden" name="generic_id" value="<?= $generic->generic_id ?>">
            <div class="modal-content">
                <div class="modal-header bg-green">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title"><i class="fa fa-link"></i> Map Brand to <?= htmlspecialchars($generic->generic_name) ?></h4>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Select Brand Drug <span class="text-red">*</span></label>
                        <select name="drug_id" class="form-control" required>
                            <option value="">-- Select Brand --</option>
                            <?php foreach ($unmapped_drugs as $d): ?>
                            <option value="<?= $d->drug_id ?>">
                                <?= htmlspecialchars($d->drug_name) ?>
                                <?php if (isset($d->dosage_form) && $d->dosage_form): ?>
                                    (<?= htmlspecialchars($d->dosage_form) ?>)
                                <?php endif; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Only unmapped drugs are shown</small>
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
                            <option value="AN">AN - Aerosol Equivalent</option>
                            <option value="AO">AO - Injectable Oil</option>
                            <option value="AP">AP - Injectable Aqueous</option>
                            <option value="AT">AT - Topical Equivalent</option>
                            <option value="BC">BC - Extended Release</option>
                            <option value="BD">BD - Documented Bioequivalence</option>
                            <option value="BE">BE - Enteric Coated</option>
                            <option value="BN">BN - Nebulizer</option>
                            <option value="BP">BP - Potential Problem</option>
                            <option value="BS">BS - Standard Not Established</option>
                            <option value="BT">BT - Topical</option>
                            <option value="BX">BX - Insufficient Data</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="checkbox">
                        <label><input type="checkbox" name="is_primary_brand" value="1"> Set as Primary Brand</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="fa fa-link"></i> Map Brand</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
$(function() {
    $('#brandsTable').DataTable({
        "order": [[0, "asc"]],
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
