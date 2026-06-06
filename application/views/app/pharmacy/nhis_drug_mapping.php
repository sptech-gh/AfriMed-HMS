<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>NHIS Drug Mapping | Hospital Management System</title>
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
        <h1><i class="fa fa-link"></i> NHIS Drug Code Mapping</h1>
        <ol class="breadcrumb">
            <li><a href="<?= base_url() ?>app/pharmacy"><i class="fa fa-medkit"></i> Pharmacy</a></li>
            <li class="active">NHIS Drug Mapping</li>
        </ol>
    </section>

    <section class="content">
        <?= isset($message) ? $message : '' ?>

        <!-- Summary Cards -->
        <div class="row">
            <div class="col-md-3 col-sm-6">
                <div class="info-box bg-green">
                    <span class="info-box-icon"><i class="fa fa-link"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Mapped Drugs</span>
                        <span class="info-box-number"><?= $stats['mapped_drugs'] ?></span>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="info-box bg-yellow">
                    <span class="info-box-icon"><i class="fa fa-unlink"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Unmapped Drugs</span>
                        <span class="info-box-number"><?= $stats['unmapped_drugs'] ?></span>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="info-box bg-aqua">
                    <span class="info-box-icon"><i class="fa fa-list"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">NHIS Tariffs</span>
                        <span class="info-box-number"><?= $stats['total_tariffs'] ?></span>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="info-box bg-purple">
                    <span class="info-box-icon"><i class="fa fa-file-text"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Claims This Month</span>
                        <span class="info-box-number">GHS <?= number_format($stats['monthly_claim_amount'], 2) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Unmapped Drugs -->
        <div class="box box-warning">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-exclamation-triangle"></i> Unmapped Drugs (<?= count($unmapped_drugs) ?>)</h3>
                <div class="box-tools pull-right">
                    <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button>
                </div>
            </div>
            <div class="box-body">
                <?php if (!empty($unmapped_drugs)): ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="unmappedTable">
                        <thead>
                            <tr>
                                <th>Drug Name</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Stock</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($unmapped_drugs as $drug): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($drug->drug_name) ?></strong>
                                    <?php if (!empty($drug->generic_name)): ?>
                                        <br><small class="text-muted"><?= htmlspecialchars($drug->generic_name) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($drug->category_name ?? 'N/A') ?></td>
                                <td>GHS <?= number_format($drug->nPrice, 2) ?></td>
                                <td><?= number_format($drug->nStock) ?></td>
                                <td>
                                    <button type="button" class="btn btn-xs btn-success btn-map" 
                                            data-drug-id="<?= $drug->drug_id ?>"
                                            data-drug-name="<?= htmlspecialchars($drug->drug_name) ?>">
                                        <i class="fa fa-link"></i> Map to NHIS
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="alert alert-success">
                    <i class="fa fa-check"></i> All drugs are mapped to NHIS tariffs.
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Mapped Drugs -->
        <div class="box box-success">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-check-circle"></i> Mapped Drugs (<?= count($mappings) ?>)</h3>
            </div>
            <div class="box-body">
                <?php if (!empty($mappings)): ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="mappedTable">
                        <thead>
                            <tr>
                                <th>HMS Drug</th>
                                <th>NHIS Code</th>
                                <th>NHIS Name</th>
                                <th>Category</th>
                                <th>Unit Tariff</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($mappings as $m): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($m->hms_drug_name) ?></strong>
                                    <br><small class="text-muted">Stock: <?= number_format($m->nStock) ?> | Price: GHS <?= number_format($m->nPrice, 2) ?></small>
                                </td>
                                <td><span class="label label-primary"><?= htmlspecialchars($m->nhis_drug_code) ?></span></td>
                                <td><?= htmlspecialchars($m->nhis_drug_name) ?></td>
                                <td>
                                    <?= htmlspecialchars($m->category) ?>
                                    <?php if ($m->is_essential): ?>
                                        <br><span class="label label-success">Essential</span>
                                    <?php endif; ?>
                                </td>
                                <td>GHS <?= number_format($m->unit_tariff, 2) ?></td>
                                <td>
                                    <a href="<?= base_url() ?>app/pharmacy/unmap_drug_nhis/<?= $m->drug_id ?>" 
                                       class="btn btn-xs btn-danger"
                                       onclick="return confirm('Remove NHIS mapping for this drug?')">
                                        <i class="fa fa-unlink"></i> Unmap
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="alert alert-info">
                    <i class="fa fa-info-circle"></i> No drugs mapped yet. Map drugs from the unmapped list above.
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Navigation -->
        <div class="row">
            <div class="col-md-4">
                <a href="<?= base_url() ?>app/pharmacy" class="btn btn-block btn-default">
                    <i class="fa fa-arrow-left"></i> Back to Worklist
                </a>
            </div>
            <div class="col-md-4">
                <a href="<?= base_url() ?>app/pharmacy/nhis_tariffs" class="btn btn-block btn-info">
                    <i class="fa fa-list"></i> View NHIS Tariffs
                </a>
            </div>
            <div class="col-md-4">
                <a href="<?= base_url() ?>app/pharmacy/nhis_claim_stats" class="btn btn-block btn-primary">
                    <i class="fa fa-bar-chart"></i> Claim Statistics
                </a>
            </div>
        </div>
    </section>
</div>

<!-- Map Drug Modal -->
<div class="modal fade" id="mapModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="<?= base_url() ?>app/pharmacy/map_drug_nhis">
            <input type="hidden" name="drug_id" id="map_drug_id">
            <div class="modal-content">
                <div class="modal-header bg-green">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title"><i class="fa fa-link"></i> Map Drug to NHIS Tariff</h4>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>HMS Drug</label>
                        <input type="text" class="form-control" id="map_drug_name" readonly>
                    </div>
                    <div class="form-group">
                        <label>Select NHIS Tariff <span class="text-red">*</span></label>
                        <select name="nhis_tariff_id" class="form-control select2-tariff" required style="width: 100%">
                            <option value="">-- Search NHIS Tariff --</option>
                        </select>
                        <small class="text-muted">Search by NHIS code or drug name</small>
                    </div>
                    <div id="tariff_preview" class="well well-sm" style="display: none;">
                        <strong>Selected Tariff:</strong>
                        <div id="tariff_details"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="fa fa-link"></i> Map Drug</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
$(function() {
    $('#unmappedTable, #mappedTable').DataTable({
        "pageLength": 25,
        "order": [[0, "asc"]]
    });

    // Map button click
    $('.btn-map').click(function() {
        var drugId = $(this).data('drug-id');
        var drugName = $(this).data('drug-name');
        $('#map_drug_id').val(drugId);
        $('#map_drug_name').val(drugName);
        $('#tariff_preview').hide();
        $('.select2-tariff').val('').trigger('change');
        $('#mapModal').modal('show');
    });

    // NHIS Tariff search
    $('.select2-tariff').select2({
        dropdownParent: $('#mapModal'),
        ajax: {
            url: '<?= base_url() ?>app/pharmacy/search_nhis_tariffs_json',
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return { term: params.term };
            },
            processResults: function(data) {
                return {
                    results: data.map(function(item) {
                        return {
                            id: item.tariff_id,
                            text: item.nhis_code + ' - ' + item.drug_name,
                            tariff: item
                        };
                    })
                };
            }
        },
        minimumInputLength: 2,
        placeholder: 'Search NHIS tariff...',
        templateResult: function(item) {
            if (!item.tariff) return item.text;
            return $('<div>' +
                '<strong>' + item.tariff.nhis_code + '</strong> - ' + item.tariff.drug_name +
                '<br><small class="text-muted">' + item.tariff.dosage_form + ' ' + item.tariff.strength + 
                ' | GHS ' + parseFloat(item.tariff.unit_price).toFixed(2) + '</small>' +
                '</div>');
        }
    }).on('select2:select', function(e) {
        var tariff = e.params.data.tariff;
        if (tariff) {
            $('#tariff_details').html(
                '<strong>Code:</strong> ' + tariff.nhis_code + '<br>' +
                '<strong>Name:</strong> ' + tariff.drug_name + '<br>' +
                '<strong>Form:</strong> ' + (tariff.dosage_form || 'N/A') + '<br>' +
                '<strong>Strength:</strong> ' + (tariff.strength || 'N/A') + '<br>' +
                '<strong>Unit Price:</strong> GHS ' + parseFloat(tariff.unit_price).toFixed(2) + '<br>' +
                '<strong>Category:</strong> ' + (tariff.category || 'N/A')
            );
            $('#tariff_preview').show();
        }
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
