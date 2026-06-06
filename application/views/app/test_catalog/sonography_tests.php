<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo isset($companyInfo->site_title) ? $companyInfo->site_title : 'HMS'; ?> — Sonography Catalog</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url()?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url()?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url()?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
    <style>
        .switch { position: relative; display: inline-block; width: 40px; height: 20px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .3s; }
        .slider:before { position: absolute; content: ""; height: 14px; width: 14px; left: 3px; bottom: 3px; background-color: white; transition: .3s; }
        input:checked + .slider { background-color: #27ae60; }
        input:checked + .slider:before { transform: translateX(20px); }
        .slider.round { border-radius: 20px; }
        .slider.round:before { border-radius: 50%; }
        .billing-link { font-size: 11px; color: #888; }
        .billing-link .linked { color: #27ae60; }
        .billing-link .unlinked { color: #c0392b; }
    </style>
</head>
<body class="skin-blue">
    <?php require_once(APPPATH.'views/include/header.php');?>
    <div class="wrapper row-offcanvas row-offcanvas-left">
        <?php require_once(APPPATH.'views/include/sidebar.php');?>
        <aside class="right-side">
            <section class="content-header">
                <h1><i class="fa fa-heartbeat"></i> Sonography Test Catalog <small>GHS/NHIS Standard Scans</small></h1>
                <ol class="breadcrumb">
                    <li><a href="<?php echo base_url(); ?>app/dashboard"><i class="fa fa-dashboard"></i> Dashboard</a></li>
                    <li class="active">Sonography Catalog</li>
                </ol>
            </section>

            <section class="content">
                <?php echo isset($message) ? $message : ''; ?>
                
                <!-- Statistics Cards -->
                <div class="row">
                    <div class="col-md-3 col-sm-6">
                        <div class="info-box bg-aqua">
                            <span class="info-box-icon"><i class="fa fa-heartbeat"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Total Scans</span>
                                <span class="info-box-number"><?php echo $stats['sono_total'] ?? 0; ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="info-box bg-green">
                            <span class="info-box-icon"><i class="fa fa-check"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Active Scans</span>
                                <span class="info-box-number"><?php echo $stats['sono_active'] ?? 0; ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="info-box bg-yellow">
                            <span class="info-box-icon"><i class="fa fa-medkit"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">NHIS Covered</span>
                                <span class="info-box-number"><?php echo $stats['sono_nhis'] ?? 0; ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="info-box bg-purple">
                            <span class="info-box-icon"><i class="fa fa-list"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Categories</span>
                                <span class="info-box-number"><?php echo count($categories ?? []); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Test List -->
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-list"></i> Sonography Scans</h3>
                        <div class="box-tools">
                            <a href="<?php echo base_url(); ?>app/test_catalog/add_sonography_test" class="btn btn-success btn-sm">
                                <i class="fa fa-plus"></i> Add New Scan
                            </a>
                        </div>
                    </div>
                    <div class="box-body">
                        <!-- Filters -->
                        <div class="row" style="margin-bottom: 15px;">
                            <div class="col-md-3">
                                <select id="categoryFilter" class="form-control">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat->category_name); ?>"><?php echo htmlspecialchars($cat->category_name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select id="nhisFilter" class="form-control">
                                    <option value="">All Coverage</option>
                                    <option value="1">NHIS Covered</option>
                                    <option value="0">Not NHIS Covered</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <input type="text" id="searchFilter" class="form-control" placeholder="Search by name or code...">
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-default btn-block" onclick="resetFilters()">
                                    <i class="fa fa-refresh"></i> Reset
                                </button>
                            </div>
                        </div>
                        
                        <div class="table-responsive">
                            <table id="sonoTestsTable" class="table table-bordered table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th width="80">Code</th>
                                        <th>Scan Name</th>
                                        <th>Category</th>
                                        <th>Body Part</th>
                                        <th width="90">Price (GH₵)</th>
                                        <th width="90">NHIS Price</th>
                                        <th width="60">NHIS</th>
                                        <th width="70">Billing</th>
                                        <th width="60">Status</th>
                                        <th width="60">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tests as $test): ?>
                                    <tr data-category="<?php echo htmlspecialchars($test->category); ?>" 
                                        data-nhis="<?php echo $test->is_nhis_covered; ?>">
                                        <td><code><?php echo htmlspecialchars($test->test_code); ?></code></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($test->test_name); ?></strong>
                                            <?php if (!empty($test->preparation)): ?>
                                            <br><small class="text-info"><i class="fa fa-info-circle"></i> <?php echo htmlspecialchars($test->preparation); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="label label-info"><?php echo htmlspecialchars($test->category); ?></span></td>
                                        <td><?php echo htmlspecialchars($test->body_part ?? '-'); ?></td>
                                        <td class="text-right"><?php echo number_format((float)$test->price, 2); ?></td>
                                        <td class="text-right"><?php echo number_format((float)($test->nhis_price ?? 0), 2); ?></td>
                                        <td class="text-center">
                                            <?php if ($test->is_nhis_covered): ?>
                                            <span class="label label-success"><i class="fa fa-check"></i></span>
                                            <?php else: ?>
                                            <span class="label label-default">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center billing-link">
                                            <?php if (isset($test->particular_id) && $test->particular_id): ?>
                                            <span class="linked" title="Linked to bill_particular #<?php echo $test->particular_id; ?>"><i class="fa fa-link"></i> #<?php echo $test->particular_id; ?></span>
                                            <?php else: ?>
                                            <span class="unlinked" title="Not linked to billing"><i class="fa fa-chain-broken"></i> None</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <label class="switch">
                                                <input type="checkbox" class="status-toggle" data-id="<?php echo $test->test_id; ?>" 
                                                       <?php echo $test->is_active ? 'checked' : ''; ?>>
                                                <span class="slider round"></span>
                                            </label>
                                        </td>
                                        <td class="text-center">
                                            <a href="<?php echo base_url(); ?>app/test_catalog/edit_sonography_test/<?php echo $test->test_id; ?>" 
                                               class="btn btn-primary btn-xs" title="Edit">
                                                <i class="fa fa-edit"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>
        </aside>
    </div>

    <script src="<?php echo base_url();?>public/js/jquery.min.js"></script>
    <script src="<?php echo base_url();?>public/js/bootstrap.min.js" type="text/javascript"></script>
    <script src="<?php echo base_url();?>public/js/AdminLTE/app.js" type="text/javascript"></script>
    <script>
    $(document).ready(function() {
        // Status toggle with CSRF
        $('.status-toggle').on('change', function() {
            var $this = $(this);
            var testId = $this.data('id');
            var isActive = $this.is(':checked') ? 1 : 0;
            var postData = { test_id: testId, is_active: isActive };
            postData['<?php echo $this->security->get_csrf_token_name(); ?>'] = '<?php echo $this->security->get_csrf_hash(); ?>';
            
            $.ajax({
                url: '<?php echo base_url(); ?>app/test_catalog/toggle_sonography_test',
                type: 'POST',
                data: postData,
                dataType: 'json',
                success: function(res) {
                    if (!res.success) { alert('Failed to update status'); $this.prop('checked', !isActive); }
                },
                error: function() { alert('Error updating status'); $this.prop('checked', !isActive); }
            });
        });
        
        // Filters
        function applyFilters() {
            var category = $('#categoryFilter').val();
            var nhis = $('#nhisFilter').val();
            var search = $('#searchFilter').val().toLowerCase();
            
            $('#sonoTestsTable tbody tr').each(function() {
                var $row = $(this);
                var showCat = !category || $row.data('category') === category;
                var showNhis = nhis === '' || String($row.data('nhis')) === nhis;
                var showSearch = !search || $row.text().toLowerCase().indexOf(search) > -1;
                $row.toggle(showCat && showNhis && showSearch);
            });
        }
        
        $('#categoryFilter, #nhisFilter').on('change', applyFilters);
        $('#searchFilter').on('keyup', applyFilters);
    });

    function resetFilters() {
        $('#categoryFilter').val('');
        $('#nhisFilter').val('');
        $('#searchFilter').val('');
        $('#sonoTestsTable tbody tr').show();
    }
    </script>
</body>
</html>
