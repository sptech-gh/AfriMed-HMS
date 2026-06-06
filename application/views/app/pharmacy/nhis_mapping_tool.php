<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>NHIS Drug Mapping Tool - Hebrew Medical Center</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.21/css/dataTables.bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css">
    <style>
        .stats-box { padding: 15px; border-radius: 4px; margin-bottom: 15px; }
        .stats-box.bg-green { background: #00a65a; color: #fff; }
        .stats-box.bg-yellow { background: #f39c12; color: #fff; }
        .stats-box.bg-blue { background: #3c8dbc; color: #fff; }
        .stats-box .stats-number { font-size: 28px; font-weight: bold; }
        .stats-box .stats-label { font-size: 14px; opacity: 0.9; }
        .match-score { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: bold; }
        .match-score.high { background: #00a65a; color: #fff; }
        .match-score.medium { background: #f39c12; color: #fff; }
        .match-score.low { background: #dd4b39; color: #fff; }
        .mapping-row { transition: background 0.2s; }
        .mapping-row:hover { background: #f5f5f5; }
        .mapping-row.selected { background: #e8f4fc; }
        .tariff-select { min-width: 300px; }
        .btn-map { min-width: 80px; }
        .filter-box { background: #f9f9f9; padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .tab-view { margin-bottom: 20px; }
        .tab-view .btn { margin-right: 5px; }
        .bulk-actions { margin-bottom: 15px; padding: 10px; background: #f0f0f0; border-radius: 4px; }
        .select-all-row { background: #fafafa; }
        .drug-info { font-size: 12px; color: #666; }
        .nhis-badge { display: inline-block; padding: 2px 6px; background: #3c8dbc; color: #fff; border-radius: 3px; font-size: 10px; }
    </style>
</head>
<body class="skin-blue">
    <?php require_once(APPPATH.'views/include/header.php');?>

    <div class="wrapper row-offcanvas row-offcanvas-left">
        <?php require_once(APPPATH.'views/include/sidebar.php');?>

        <aside class="right-side">
            <section class="content-header">
                <h1>
                    <i class="fa fa-link"></i> NHIS Drug Mapping Tool
                    <small>Map HMS drugs to NHIS tariffs</small>
                </h1>
                <ol class="breadcrumb">
                    <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                    <li><a href="<?php echo base_url()?>app/pharmacy">Pharmacy</a></li>
                    <li class="active">NHIS Drug Mapping</li>
                </ol>
            </section>

            <section class="content">
                <?php if (!empty($message)) echo $message; ?>

                <!-- Statistics Row -->
                <div class="row">
                    <div class="col-md-4">
                        <div class="stats-box bg-yellow">
                            <div class="stats-number"><?php echo isset($stats['unmapped_drugs']) ? $stats['unmapped_drugs'] : 0; ?></div>
                            <div class="stats-label"><i class="fa fa-exclamation-triangle"></i> Unmapped Drugs</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-box bg-green">
                            <div class="stats-number"><?php echo isset($stats['mapped_drugs']) ? $stats['mapped_drugs'] : 0; ?></div>
                            <div class="stats-label"><i class="fa fa-check-circle"></i> Mapped Drugs</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-box bg-blue">
                            <div class="stats-number"><?php echo isset($stats['total_tariffs']) ? $stats['total_tariffs'] : 0; ?></div>
                            <div class="stats-label"><i class="fa fa-list"></i> NHIS Tariffs Available</div>
                        </div>
                    </div>
                </div>

                <!-- View Toggle -->
                <div class="tab-view">
                    <a href="<?php echo base_url(); ?>app/pharmacy/nhis_mapping_tool?view=unmapped" 
                       class="btn <?php echo ($filters['view'] !== 'mapped') ? 'btn-primary' : 'btn-default'; ?>">
                        <i class="fa fa-exclamation-circle"></i> Unmapped Drugs (<?php echo isset($stats['unmapped_drugs']) ? $stats['unmapped_drugs'] : 0; ?>)
                    </a>
                    <a href="<?php echo base_url(); ?>app/pharmacy/nhis_mapping_tool?view=mapped" 
                       class="btn <?php echo ($filters['view'] === 'mapped') ? 'btn-primary' : 'btn-default'; ?>">
                        <i class="fa fa-check-circle"></i> Mapped Drugs (<?php echo isset($stats['mapped_drugs']) ? $stats['mapped_drugs'] : 0; ?>)
                    </a>
                    <a href="<?php echo base_url(); ?>app/pharmacy/export_unmapped_csv" class="btn btn-success pull-right">
                        <i class="fa fa-download"></i> Export Unmapped CSV
                    </a>
                </div>

                <!-- Filters -->
                <div class="filter-box">
                    <form method="get" action="<?php echo base_url(); ?>app/pharmacy/nhis_mapping_tool" class="form-inline">
                        <input type="hidden" name="view" value="<?php echo htmlspecialchars($filters['view']); ?>">
                        <div class="form-group">
                            <input type="text" name="search" class="form-control" placeholder="Search drug name..." 
                                   value="<?php echo htmlspecialchars($filters['search'] ?: ''); ?>" style="width: 250px;">
                        </div>
                        <?php if ($filters['view'] !== 'mapped'): ?>
                        <div class="form-group">
                            <select name="category_id" class="form-control">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat->category_id; ?>" <?php echo ($filters['category_id'] == $cat->category_id) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat->category_name); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> Filter</button>
                        <a href="<?php echo base_url(); ?>app/pharmacy/nhis_mapping_tool?view=<?php echo $filters['view']; ?>" class="btn btn-default">
                            <i class="fa fa-refresh"></i> Reset
                        </a>
                    </form>
                </div>

                <?php if ($filters['view'] !== 'mapped'): ?>
                <!-- Bulk Actions (Unmapped View Only) -->
                <div class="bulk-actions">
                    <button type="button" class="btn btn-warning" id="btnAutoMatch" onclick="runAutoMatch()">
                        <i class="fa fa-magic"></i> Auto-Match Selected
                    </button>
                    <a href="<?php echo base_url(); ?>app/pharmacy/apply_auto_mapping" class="btn btn-success" 
                       onclick="return confirm('This will automatically map all drugs that have a match score >= 50%. Continue?');">
                        <i class="fa fa-bolt"></i> Auto-Map All
                    </a>
                    <button type="button" class="btn btn-primary" id="btnBulkMap" onclick="bulkMapSelected()">
                        <i class="fa fa-link"></i> Map Selected
                    </button>
                    <span class="text-muted" style="margin-left: 15px;">
                        <span id="selectedCount">0</span> drugs selected
                    </span>
                </div>
                <?php endif; ?>

                <!-- Drug Table -->
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">
                            <?php echo ($filters['view'] === 'mapped') ? 'Mapped Drugs' : 'Unmapped Drugs'; ?>
                            <small>(<?php echo $total_count; ?> total)</small>
                        </h3>
                    </div>
                    <div class="box-body">
                        <form id="mappingForm" method="post" action="<?php echo base_url(); ?>app/pharmacy/bulk_nhis_mapping">
                            <input type="hidden" name="mappings" id="mappingsInput">
                            
                            <table class="table table-bordered table-hover" id="drugTable">
                                <thead>
                                    <tr class="select-all-row">
                                        <?php if ($filters['view'] !== 'mapped'): ?>
                                        <th style="width: 30px;">
                                            <input type="checkbox" id="selectAll" onclick="toggleSelectAll()">
                                        </th>
                                        <?php endif; ?>
                                        <th>HMS Drug</th>
                                        <th>Generic Name</th>
                                        <th>Strength</th>
                                        <th>Form</th>
                                        <?php if ($filters['view'] === 'mapped'): ?>
                                        <th>NHIS Code</th>
                                        <th>NHIS Tariff</th>
                                        <th>Action</th>
                                        <?php else: ?>
                                        <th>NHIS Tariff Match</th>
                                        <th>Action</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($drugs)): ?>
                                    <tr>
                                        <td colspan="<?php echo ($filters['view'] === 'mapped') ? 8 : 8; ?>" class="text-center text-muted">
                                            No drugs found.
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($drugs as $drug): ?>
                                    <tr class="mapping-row" data-drug-id="<?php echo $drug->drug_id; ?>">
                                        <?php if ($filters['view'] !== 'mapped'): ?>
                                        <td>
                                            <input type="checkbox" class="drug-checkbox" value="<?php echo $drug->drug_id; ?>" onchange="updateSelectedCount()">
                                        </td>
                                        <?php endif; ?>
                                        <td>
                                            <strong><?php echo htmlspecialchars($drug->drug_name); ?></strong>
                                            <div class="drug-info">
                                                Price: GH₵<?php echo number_format($drug->nPrice ?: 0, 2); ?> | 
                                                Stock: <?php echo (int)($drug->nStock ?: 0); ?>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($drug->generic_name ?: '-'); ?></td>
                                        <td><?php echo htmlspecialchars($drug->strength ?: '-'); ?></td>
                                        <td><?php echo htmlspecialchars($drug->dosage_form ?: '-'); ?></td>
                                        
                                        <?php if ($filters['view'] === 'mapped'): ?>
                                        <td>
                                            <span class="nhis-badge"><?php echo htmlspecialchars($drug->nhis_drug_code); ?></span>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($drug->nhis_drug_name); ?><br>
                                            <small class="text-success">GH₵<?php echo number_format($drug->nhis_unit_tariff, 2); ?></small>
                                        </td>
                                        <td>
                                            <a href="<?php echo base_url(); ?>app/pharmacy/unmap_drug_nhis/<?php echo $drug->drug_id; ?>" 
                                               class="btn btn-xs btn-danger" 
                                               onclick="return confirm('Remove NHIS mapping for this drug?');">
                                                <i class="fa fa-unlink"></i> Unmap
                                            </a>
                                        </td>
                                        <?php else: ?>
                                        <td>
                                            <select class="form-control tariff-select tariff-dropdown" data-drug-id="<?php echo $drug->drug_id; ?>">
                                                <option value="">-- Select NHIS Tariff --</option>
                                                <?php foreach ($nhis_tariffs as $tariff): ?>
                                                <option value="<?php echo $tariff->tariff_id; ?>" 
                                                        data-code="<?php echo htmlspecialchars($tariff->nhis_code); ?>"
                                                        data-price="<?php echo $tariff->unit_price; ?>">
                                                    <?php echo htmlspecialchars($tariff->nhis_code . ' - ' . $tariff->drug_name . ' (GH₵' . number_format($tariff->unit_price, 2) . ')'); ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="suggested-match" id="suggest-<?php echo $drug->drug_id; ?>" style="margin-top: 5px; display: none;">
                                                <small class="text-info"><i class="fa fa-lightbulb-o"></i> Suggested: <span class="suggested-name"></span></small>
                                                <span class="match-score"></span>
                                            </div>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-xs btn-success btn-map" 
                                                    onclick="mapSingleDrug(<?php echo $drug->drug_id; ?>)">
                                                <i class="fa fa-link"></i> Map
                                            </button>
                                        </td>
                                        <?php endif; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </form>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                        <nav>
                            <ul class="pagination">
                                <?php if ($page > 1): ?>
                                <li>
                                    <a href="<?php echo base_url(); ?>app/pharmacy/nhis_mapping_tool?view=<?php echo $filters['view']; ?>&search=<?php echo urlencode($filters['search'] ?: ''); ?>&category_id=<?php echo $filters['category_id']; ?>&page=<?php echo $page - 1; ?>">
                                        &laquo; Prev
                                    </a>
                                </li>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <li class="<?php echo ($i == $page) ? 'active' : ''; ?>">
                                    <a href="<?php echo base_url(); ?>app/pharmacy/nhis_mapping_tool?view=<?php echo $filters['view']; ?>&search=<?php echo urlencode($filters['search'] ?: ''); ?>&category_id=<?php echo $filters['category_id']; ?>&page=<?php echo $i; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                <li>
                                    <a href="<?php echo base_url(); ?>app/pharmacy/nhis_mapping_tool?view=<?php echo $filters['view']; ?>&search=<?php echo urlencode($filters['search'] ?: ''); ?>&category_id=<?php echo $filters['category_id']; ?>&page=<?php echo $page + 1; ?>">
                                        Next &raquo;
                                    </a>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                        <?php endif; ?>
                    </div>
                </div>

            </section>
        </aside>
    </div>

    <!-- Single Map Form (Hidden) -->
    <form id="singleMapForm" method="post" action="<?php echo base_url(); ?>app/pharmacy/save_nhis_mapping" style="display: none;">
        <input type="hidden" name="drug_id" id="singleDrugId">
        <input type="hidden" name="tariff_id" id="singleTariffId">
    </form>

    <script src="<?php echo base_url()?>public/js/jquery.min.js"></script>
    <script src="<?php echo base_url()?>public/js/bootstrap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize Select2 for tariff dropdowns
            $('.tariff-dropdown').select2({
                placeholder: '-- Select NHIS Tariff --',
                allowClear: true,
                width: '100%'
            });
        });

        function toggleSelectAll() {
            var checked = $('#selectAll').is(':checked');
            $('.drug-checkbox').prop('checked', checked);
            updateSelectedCount();
        }

        function updateSelectedCount() {
            var count = $('.drug-checkbox:checked').length;
            $('#selectedCount').text(count);
        }

        function mapSingleDrug(drugId) {
            var tariffId = $('select[data-drug-id="' + drugId + '"]').val();
            if (!tariffId) {
                alert('Please select an NHIS tariff first.');
                return;
            }
            $('#singleDrugId').val(drugId);
            $('#singleTariffId').val(tariffId);
            $('#singleMapForm').submit();
        }

        function bulkMapSelected() {
            var mappings = [];
            $('.drug-checkbox:checked').each(function() {
                var drugId = $(this).val();
                var tariffId = $('select[data-drug-id="' + drugId + '"]').val();
                if (tariffId) {
                    mappings.push({ drug_id: parseInt(drugId), tariff_id: parseInt(tariffId) });
                }
            });

            if (mappings.length === 0) {
                alert('Please select drugs and choose NHIS tariffs for them.');
                return;
            }

            $('#mappingsInput').val(JSON.stringify(mappings));
            $('#mappingForm').submit();
        }

        function runAutoMatch() {
            var drugIds = [];
            $('.drug-checkbox:checked').each(function() {
                drugIds.push(parseInt($(this).val()));
            });

            if (drugIds.length === 0) {
                alert('Please select drugs to auto-match.');
                return;
            }

            $('#btnAutoMatch').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Matching...');

            $.ajax({
                url: '<?php echo base_url(); ?>app/pharmacy/auto_suggest_mapping',
                method: 'POST',
                data: { drug_ids: JSON.stringify(drugIds) },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.matches.length > 0) {
                        // Apply suggestions to dropdowns
                        response.matches.forEach(function(match) {
                            var $select = $('select[data-drug-id="' + match.drug_id + '"]');
                            $select.val(match.suggested_tariff_id).trigger('change');

                            // Show suggestion info
                            var $suggest = $('#suggest-' + match.drug_id);
                            $suggest.find('.suggested-name').text(match.suggested_nhis_name);
                            
                            var scoreClass = match.match_score >= 80 ? 'high' : (match.match_score >= 60 ? 'medium' : 'low');
                            $suggest.find('.match-score').attr('class', 'match-score ' + scoreClass).text(match.match_score + '%');
                            $suggest.show();
                        });
                        alert('Found ' + response.matches.length + ' matches. Review and click "Map Selected" to apply.');
                    } else {
                        alert('No matches found for selected drugs.');
                    }
                },
                error: function() {
                    alert('Error running auto-match. Please try again.');
                },
                complete: function() {
                    $('#btnAutoMatch').prop('disabled', false).html('<i class="fa fa-magic"></i> Auto-Match Selected');
                }
            });
        }
    </script>
</body>
</html>
