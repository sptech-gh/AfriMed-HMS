<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed'); ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>NHIS Drug Mapping Tool</title>
    <?php require_once(APPPATH.'views/include/header.php');?>
    <link rel="stylesheet" href="<?=base_url()?>public/css/bootstrap.min.css">
    <style>
        .unmapped-row { background-color: #fff3cd; }
        .mapped-row { background-color: #d4edda; }
        .match-score { font-weight: bold; }
        .score-high { color: #28a745; }
        .score-medium { color: #ffc107; }
        .score-low { color: #dc3545; }
    </style>
</head>
<body class="skin-blue">
    <?php require_once(APPPATH.'views/include/sidebar.php');?>
    <aside class="right-side">
        <section class="content-header">
            <h1>NHIS Drug Mapping Tool</h1>
            <p class="text-muted">Map HMS drugs to NHIS drug tariffs for claim generation</p>
        </section>

        <section class="content">
            <!-- Stats -->
            <div class="row">
                <div class="col-lg-3 col-xs-6">
                    <div class="small-box bg-aqua">
                        <div class="inner">
                            <h3><?=$total_drugs?></h3>
                            <p>Total Drugs</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-xs-6">
                    <div class="small-box bg-green">
                        <div class="inner">
                            <h3><?=$mapped_drugs?></h3>
                            <p>Mapped</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-xs-6">
                    <div class="small-box bg-yellow">
                        <div class="inner">
                            <h3><?=$unmapped_drugs?></h3>
                            <p>Unmapped</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-xs-6">
                    <div class="small-box bg-blue">
                        <div class="inner">
                            <h3><?=round(($mapped_drugs/$total_drugs)*100, 1)?>%</h3>
                            <p>Coverage</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="box">
                <div class="box-header">
                    <h3 class="box-title">Bulk Actions</h3>
                </div>
                <div class="box-body">
                    <a href="<?=base_url()?>app/nhis/auto_map_drugs" class="btn btn-primary">
                        <i class="fa fa-magic"></i> Auto-Map by Name Similarity
                    </a>
                    <a href="<?=base_url()?>app/nhis/drug_mapping_export" class="btn btn-info">
                        <i class="fa fa-download"></i> Export Unmapped List
                    </a>
                </div>
            </div>

            <!-- Unmapped Drugs -->
            <div class="box box-warning">
                <div class="box-header">
                    <h3 class="box-title">Unmapped Drugs (<?=count($unmapped)?>)</h3>
                    <div class="box-tools">
                        <input type="text" id="searchUnmapped" class="form-control" placeholder="Search drugs...">
                    </div>
                </div>
                <div class="box-body table-responsive">
                    <table class="table table-bordered table-striped" id="unmappedTable">
                        <thead>
                            <tr>
                                <th>HMS Drug Name</th>
                                <th>Generic Name</th>
                                <th>Dosage Form</th>
                                <th>Strength</th>
                                <th>Suggested NHIS Match</th>
                                <th>Score</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($unmapped as $drug): ?>
                            <tr class="unmapped-row" data-drug-id="<?=$drug->drug_id?>">
                                <td><strong><?=htmlspecialchars($drug->drug_name)?></strong></td>
                                <td><?=htmlspecialchars($drug->generic_name ?? '')?></td>
                                <td><?=htmlspecialchars($drug->dosage_form ?? '')?></td>
                                <td><?=htmlspecialchars($drug->strength ?? '')?></td>
                                <td>
                                    <?php if(isset($drug->suggested_match)): ?>
                                        <select class="form-control input-sm nhis-select">
                                            <option value="">-- Select NHIS Drug --</option>
                                            <?php foreach($nhis_tariffs as $tariff): ?>
                                            <option value="<?=$tariff->tariff_id?>" 
                                                data-code="<?=$tariff->nhis_code?>"
                                                data-price="<?=$tariff->unit_price?>"
                                                <?=($drug->suggested_match->tariff_id == $tariff->tariff_id) ? 'selected' : ''?>>
                                                <?=$tariff->nhis_code?> - <?=$tariff->drug_name?> (GHS <?=number_format($tariff->unit_price, 2)?>)
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php else: ?>
                                        <select class="form-control input-sm nhis-select">
                                            <option value="">-- Select NHIS Drug --</option>
                                            <?php foreach($nhis_tariffs as $tariff): ?>
                                            <option value="<?=$tariff->tariff_id?>" 
                                                data-code="<?=$tariff->nhis_code?>"
                                                data-price="<?=$tariff->unit_price?>">
                                                <?=$tariff->nhis_code?> - <?=$tariff->drug_name?> (GHS <?=number_format($tariff->unit_price, 2)?>)
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php endif; ?>
                                </td>
                                <td class="match-score">
                                    <?php if(isset($drug->suggested_match)): ?>
                                        <?php $score = $drug->match_score; ?>
                                        <span class="<?=$score >= 80 ? 'score-high' : ($score >= 50 ? 'score-medium' : 'score-low')?>">
                                            <?=$score?>%
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-success btn-sm btn-map" data-drug-id="<?=$drug->drug_id?>">
                                        <i class="fa fa-check"></i> Map
                                    </button>
                                    <button class="btn btn-danger btn-sm btn-skip" data-drug-id="<?=$drug->drug_id?>">
                                        <i class="fa fa-ban"></i> Not NHIS
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Mapped Drugs -->
            <div class="box box-success">
                <div class="box-header">
                    <h3 class="box-title">Recently Mapped Drugs</h3>
                </div>
                <div class="box-body table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>HMS Drug</th>
                                <th>NHIS Code</th>
                                <th>NHIS Drug Name</th>
                                <th>Tariff (GHS)</th>
                                <th>Mapped Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($recently_mapped as $map): ?>
                            <tr class="mapped-row">
                                <td><?=htmlspecialchars($map->drug_name)?></td>
                                <td><code><?=$map->nhis_drug_code?></code></td>
                                <td><?=htmlspecialchars($map->nhis_drug_name)?></td>
                                <td><?=number_format($map->unit_tariff, 2)?></td>
                                <td><?=date('Y-m-d H:i', strtotime($map->created_at))?></td>
                                <td>
                                    <button class="btn btn-warning btn-sm btn-unmap" data-mapping-id="<?=$map->mapping_id?>">
                                        <i class="fa fa-undo"></i> Unmap
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </aside>

    <script src="<?=base_url()?>public/js/jquery.min.js"></script>
    <script src="<?=base_url()?>public/js/bootstrap.min.js"></script>
    <script>
    $(function() {
        // Search filter
        $('#searchUnmapped').on('keyup', function() {
            var value = $(this).val().toLowerCase();
            $('#unmappedTable tbody tr').filter(function() {
                $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
            });
        });

        // Map button
        $('.btn-map').on('click', function() {
            var drugId = $(this).data('drug-id');
            var row = $(this).closest('tr');
            var select = row.find('.nhis-select');
            var tariffId = select.val();
            var nhisCode = select.find(':selected').data('code');
            var tariffPrice = select.find(':selected').data('price');
            
            if (!tariffId) {
                alert('Please select an NHIS drug first');
                return;
            }

            $.ajax({
                url: '<?=base_url()?>app/nhis/map_drug_ajax',
                type: 'POST',
                data: {
                    drug_id: drugId,
                    tariff_id: tariffId,
                    nhis_code: nhisCode,
                    unit_tariff: tariffPrice,
                    <?=$this->security->get_csrf_token_name()?>: '<?=$this->security->get_csrf_hash()?>'
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        row.fadeOut(300, function() {
                            $(this).remove();
                        });
                        alert('Drug mapped successfully!');
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function() {
                    alert('Failed to map drug. Please try again.');
                }
            });
        });

        // Skip button (mark as not NHIS covered)
        $('.btn-skip').on('click', function() {
            var drugId = $(this).data('drug-id');
            var row = $(this).closest('tr');
            
            if (!confirm('Mark this drug as NOT NHIS covered?')) return;

            $.ajax({
                url: '<?=base_url()?>app/nhis/mark_drug_not_nhis_ajax',
                type: 'POST',
                data: {
                    drug_id: drugId,
                    <?=$this->security->get_csrf_token_name()?>: '<?=$this->security->get_csrf_hash()?>'
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        row.fadeOut(300, function() {
                            $(this).remove();
                        });
                    } else {
                        alert('Error: ' + response.message);
                    }
                }
            });
        });
    });
    </script>
</body>
</html>
