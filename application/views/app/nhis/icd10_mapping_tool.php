<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed'); ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>NHIS ICD-10 Diagnosis Mapping Tool</title>
    <?php require_once(APPPATH.'views/include/header.php');?>
    <link rel="stylesheet" href="<?=base_url()?>public/css/bootstrap.min.css">
    <style>
        .unmapped-row { background-color: #fff3cd; }
        .mapped-row { background-color: #d4edda; }
        .icd-select { width: 100%; }
        .category-badge { font-size: 11px; }
    </style>
</head>
<body class="skin-blue">
    <?php require_once(APPPATH.'views/include/sidebar.php');?>
    <aside class="right-side">
        <section class="content-header">
            <h1>ICD-10 Diagnosis Mapping Tool</h1>
            <p class="text-muted">Map HMS diagnoses to ICD-10 codes for NHIS claims</p>
        </section>

        <section class="content">
            <!-- Stats -->
            <div class="row">
                <div class="col-lg-3 col-xs-6">
                    <div class="small-box bg-aqua">
                        <div class="inner">
                            <h3><?=$total_diagnoses?></h3>
                            <p>Total Diagnoses</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-xs-6">
                    <div class="small-box bg-green">
                        <div class="inner">
                            <h3><?=$mapped_diagnoses?></h3>
                            <p>ICD-10 Mapped</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-xs-6">
                    <div class="small-box bg-yellow">
                        <div class="inner">
                            <h3><?=$unmapped_count?></h3>
                            <p>Unmapped</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-xs-6">
                    <div class="small-box bg-blue">
                        <div class="inner">
                            <h3><?=round(($mapped_diagnoses/$total_diagnoses)*100, 1)?>%</h3>
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
                    <a href="<?=base_url()?>app/nhis/auto_map_diagnoses" class="btn btn-primary" onclick="return confirm('This will auto-map diagnoses based on name patterns. Continue?')">
                        <i class="fa fa-magic"></i> Auto-Map by Pattern Matching
                    </a>
                    <a href="<?=base_url()?>app/nhis/icd10_export_unmapped" class="btn btn-info">
                        <i class="fa fa-download"></i> Export Unmapped List
                    </a>
                    <a href="<?=base_url()?>app/nhis/seed_icd10_codes" class="btn btn-warning" onclick="return confirm('This will add 85 common ICD-10 codes. Continue?')">
                        <i class="fa fa-database"></i> Seed Additional ICD-10 Codes
                    </a>
                </div>
            </div>

            <!-- Pattern Quick Reference -->
            <div class="box box-info">
                <div class="box-header">
                    <h3 class="box-title">Common Diagnosis Patterns</h3>
                </div>
                <div class="box-body">
                    <div class="row">
                        <div class="col-md-3">
                            <h5>Infectious</h5>
                            <ul class="list-unstyled">
                                <li><code>B54</code> - Malaria</li>
                                <li><code>A01</code> - Typhoid</li>
                                <li><code>J18</code> - Pneumonia</li>
                                <li><code>N39</code> - UTI</li>
                            </ul>
                        </div>
                        <div class="col-md-3">
                            <h5>Respiratory</h5>
                            <ul class="list-unstyled">
                                <li><code>J45</code> - Asthma</li>
                                <li><code>J00</code> - Common Cold</li>
                                <li><code>J02</code> - Pharyngitis</li>
                                <li><code>J03</code> - Tonsillitis</li>
                            </ul>
                        </div>
                        <div class="col-md-3">
                            <h5>Cardiovascular</h5>
                            <ul class="list-unstyled">
                                <li><code>I10</code> - Hypertension</li>
                                <li><code>I20</code> - Angina</li>
                                <li><code>I21</code> - MI</li>
                                <li><code>I50</code> - Heart Failure</li>
                            </ul>
                        </div>
                        <div class="col-md-3">
                            <h5>Endocrine</h5>
                            <ul class="list-unstyled">
                                <li><code>E11</code> - T2DM</li>
                                <li><code>E10</code> - T1DM</li>
                                <li><code>E03</code> - Hypothyroid</li>
                                <li><code>E05</code> - Hyperthyroid</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Unmapped Diagnoses -->
            <div class="box box-warning">
                <div class="box-header">
                    <h3 class="box-title">Unmapped Diagnoses (<?=$unmapped_count?>)</h3>
                    <div class="box-tools">
                        <input type="text" id="searchUnmapped" class="form-control" placeholder="Search diagnoses...">
                    </div>
                </div>
                <div class="box-body table-responsive">
                    <table class="table table-bordered table-striped" id="unmappedTable">
                        <thead>
                            <tr>
                                <th width="25%">HMS Diagnosis</th>
                                <th width="10%">Category</th>
                                <th width="40%">ICD-10 Code</th>
                                <th width="15%">Suggested</th>
                                <th width="10%">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($unmapped as $diag): ?>
                            <tr class="unmapped-row" data-diag-id="<?=$diag->diagnosis_id?>">
                                <td><strong><?=htmlspecialchars($diag->diagnosis_name)?></strong></td>
                                <td>
                                    <span class="label label-default category-badge">
                                        <?=htmlspecialchars($diag->category ?? 'General')?>
                                    </span>
                                </td>
                                <td>
                                    <select class="form-control input-sm icd-select">
                                        <option value="">-- Select ICD-10 Code --</option>
                                        <?php foreach($icd_codes as $icd): ?>
                                        <option value="<?=$icd['code']?>" 
                                            <?=($diag->suggested_code == $icd['code']) ? 'selected' : ''?>>
                                            <?=$icd['code']?> - <?=htmlspecialchars($icd['description'])?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <?php if($diag->suggested_code): ?>
                                        <span class="text-success">
                                            <i class="fa fa-check-circle"></i> 
                                            <?=$diag->suggested_code?> (<?=$diag->match_score?>%)
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">No suggestion</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-success btn-sm btn-map" data-diag-id="<?=$diag->diagnosis_id?>">
                                        <i class="fa fa-check"></i> Map
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recently Mapped -->
            <div class="box box-success">
                <div class="box-header">
                    <h3 class="box-title">Recently Mapped Diagnoses</h3>
                </div>
                <div class="box-body table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Diagnosis</th>
                                <th>ICD-10 Code</th>
                                <th>Description</th>
                                <th>Mapped Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($recently_mapped as $d): ?>
                            <tr class="mapped-row">
                                <td><?=htmlspecialchars($d->diagnosis_name)?></td>
                                <td><code><?=$d->icd_code?></code></td>
                                <td><?=htmlspecialchars($d->icd_description ?? '')?></td>
                                <td><?=date('Y-m-d', strtotime($d->updated_at ?? 'now'))?></td>
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
            var diagId = $(this).data('diag-id');
            var row = $(this).closest('tr');
            var select = row.find('.icd-select');
            var icdCode = select.val();
            
            if (!icdCode) {
                alert('Please select an ICD-10 code first');
                return;
            }

            $.ajax({
                url: '<?=base_url()?>app/nhis/map_diagnosis_ajax',
                type: 'POST',
                data: {
                    diagnosis_id: diagId,
                    icd_code: icdCode,
                    <?=$this->security->get_csrf_token_name()?>: '<?=$this->security->get_csrf_hash()?>'
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        row.fadeOut(300, function() {
                            $(this).remove();
                        });
                        alert('Diagnosis mapped successfully!');
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function() {
                    alert('Failed to map diagnosis. Please try again.');
                }
            });
        });
    });
    </script>
</body>
</html>
