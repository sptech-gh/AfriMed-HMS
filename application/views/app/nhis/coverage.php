<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>NHIS Coverage Management - Hebrew Medical Center</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">

    <link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />

    <style>
        .coverage-badge { font-size: 11px; }
        .table td { vertical-align: middle !important; }
    </style>
</head>
<body class="skin-blue">
    <?php require_once(APPPATH.'views/include/header.php');?>

    <div class="wrapper row-offcanvas row-offcanvas-left">
        <?php require_once(APPPATH.'views/include/sidebar.php');?>

        <aside class="right-side">
            <section class="content-header">
                <h1>NHIS Coverage Management</h1>
                <ol class="breadcrumb">
                    <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                    <li><a href="<?php echo base_url()?>app/nhis">NHIS</a></li>
                    <li class="active">Coverage</li>
                </ol>
            </section>

            <section class="content">
                <?php if ($this->session->flashdata('success')): ?>
                    <div class="alert alert-success alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                        <?php echo $this->session->flashdata('success'); ?>
                    </div>
                <?php endif; ?>

                <!-- Type Tabs -->
                <div class="nav-tabs-custom">
                    <ul class="nav nav-tabs">
                        <?php 
                            $types = isset($item_types) ? $item_types : array('drug' => 'Drugs', 'lab' => 'Laboratory', 'radiology' => 'Radiology', 'service' => 'Services');
                            $currentType = isset($item_type) ? $item_type : 'drug';
                        ?>
                        <?php foreach ($types as $key => $label): ?>
                            <li class="<?php echo $currentType == $key ? 'active' : ''; ?>">
                                <a href="<?php echo base_url('app/nhis/coverage?type=' . $key); ?>">
                                    <?php echo $label; ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane active">
                            <!-- Search -->
                            <div class="row" style="margin-bottom: 15px;">
                                <div class="col-md-6">
                                    <form method="get" class="form-inline">
                                        <input type="hidden" name="type" value="<?php echo $currentType; ?>">
                                        <div class="input-group">
                                            <input type="text" name="search" class="form-control" 
                                                   placeholder="Search by name or NHIS code..."
                                                   value="<?php echo isset($search) ? $search : ''; ?>">
                                            <span class="input-group-btn">
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="fa fa-search"></i>
                                                </button>
                                            </span>
                                        </div>
                                    </form>
                                </div>
                                <div class="col-md-6 text-right">
                                    <button type="button" class="btn btn-success" data-toggle="modal" data-target="#addCoverageModal">
                                        <i class="fa fa-plus"></i> Add Coverage Item
                                    </button>
                                </div>
                            </div>

                            <!-- Coverage Table -->
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover table-striped">
                                    <thead>
                                        <tr>
                                            <th>Item Name</th>
                                            <th>NHIS Code</th>
                                            <th class="text-center">Coverage %</th>
                                            <th class="text-right">Max Limit</th>
                                            <th class="text-center">Pre-Auth</th>
                                            <th>Formulary</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($coverage_items)): ?>
                                            <?php foreach ($coverage_items as $item): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo $item->item_name; ?></strong>
                                                        <br><small class="text-muted">ID: <?php echo $item->item_id; ?></small>
                                                    </td>
                                                    <td><?php echo $item->nhis_code ?: '-'; ?></td>
                                                    <td class="text-center">
                                                        <?php
                                                            $pct = (float)$item->coverage_percentage;
                                                            $pctClass = 'success';
                                                            if ($pct < 50) $pctClass = 'danger';
                                                            elseif ($pct < 80) $pctClass = 'warning';
                                                        ?>
                                                        <span class="label label-<?php echo $pctClass; ?> coverage-badge">
                                                            <?php echo number_format($pct, 0); ?>%
                                                        </span>
                                                    </td>
                                                    <td class="text-right">
                                                        <?php echo $item->max_limit ? 'GHS ' . number_format($item->max_limit, 2) : '-'; ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <?php if ($item->requires_preauth): ?>
                                                            <span class="label label-warning"><i class="fa fa-check"></i></span>
                                                        <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                            $formClass = 'success';
                                                            if ($item->formulary_status == 'restricted') $formClass = 'warning';
                                                            elseif ($item->formulary_status == 'not_listed') $formClass = 'danger';
                                                        ?>
                                                        <span class="label label-<?php echo $formClass; ?>">
                                                            <?php echo ucfirst(str_replace('_', ' ', $item->formulary_status)); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if ($item->is_active): ?>
                                                            <span class="label label-success">Active</span>
                                                        <?php else: ?>
                                                            <span class="label label-default">Inactive</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <button type="button" class="btn btn-xs btn-primary edit-coverage"
                                                                data-id="<?php echo $item->id; ?>"
                                                                data-item='<?php echo json_encode($item); ?>'>
                                                            <i class="fa fa-edit"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="8" class="text-center text-muted">
                                                    No coverage items found for this type.
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

            </section>
        </aside>
    </div>

    <!-- Add/Edit Coverage Modal -->
    <div class="modal fade" id="addCoverageModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="coverageForm" method="post" action="<?php echo base_url('app/nhis/save_coverage'); ?>">
                    <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                        <h4 class="modal-title"><i class="fa fa-shield"></i> <span id="modalTitle">Add Coverage Item</span></h4>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id" id="coverage_id">
                        <input type="hidden" name="item_type" id="item_type" value="<?php echo $currentType; ?>">
                        
                        <div class="form-group">
                            <label>Item ID <span class="text-danger">*</span></label>
                            <input type="number" name="item_id" id="item_id" class="form-control" required>
                            <small class="text-muted">The ID from the source table (drug_id, lab_id, etc.)</small>
                        </div>
                        
                        <div class="form-group">
                            <label>Item Name <span class="text-danger">*</span></label>
                            <input type="text" name="item_name" id="item_name" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label>NHIS Code</label>
                            <input type="text" name="nhis_code" id="nhis_code" class="form-control">
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Coverage Percentage</label>
                                    <div class="input-group">
                                        <input type="number" name="coverage_percentage" id="coverage_percentage" 
                                               class="form-control" value="100" min="0" max="100" step="0.01">
                                        <span class="input-group-addon">%</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Max Limit (GHS)</label>
                                    <input type="number" name="max_limit" id="max_limit" 
                                           class="form-control" step="0.01" placeholder="No limit">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Formulary Status</label>
                            <select name="formulary_status" id="formulary_status" class="form-control">
                                <option value="approved">Approved</option>
                                <option value="restricted">Restricted</option>
                                <option value="not_listed">Not Listed</option>
                            </select>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="checkbox">
                                    <label>
                                        <input type="checkbox" name="requires_preauth" id="requires_preauth" value="1">
                                        Requires Pre-Authorization
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="checkbox">
                                    <label>
                                        <input type="checkbox" name="is_active" id="is_active" value="1" checked>
                                        Active
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Coverage</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="<?php echo base_url();?>public/js/jquery.min.js"></script>
    <script src="<?php echo base_url();?>public/js/bootstrap.min.js" type="text/javascript"></script>
    <script src="<?php echo base_url();?>public/js/AdminLTE/app.js" type="text/javascript"></script>
    <script>
        $(document).ready(function() {
            // Edit coverage
            $('.edit-coverage').click(function() {
                var item = $(this).data('item');
                $('#modalTitle').text('Edit Coverage Item');
                $('#coverage_id').val(item.id);
                $('#item_type').val(item.item_type);
                $('#item_id').val(item.item_id);
                $('#item_name').val(item.item_name);
                $('#nhis_code').val(item.nhis_code);
                $('#coverage_percentage').val(item.coverage_percentage);
                $('#max_limit').val(item.max_limit);
                $('#formulary_status').val(item.formulary_status);
                $('#requires_preauth').prop('checked', item.requires_preauth == 1);
                $('#is_active').prop('checked', item.is_active == 1);
                $('#addCoverageModal').modal('show');
            });

            // Reset form on modal close
            $('#addCoverageModal').on('hidden.bs.modal', function() {
                $('#modalTitle').text('Add Coverage Item');
                $('#coverageForm')[0].reset();
                $('#coverage_id').val('');
                $('#is_active').prop('checked', true);
            });

            // Form submission via AJAX
            $('#coverageForm').submit(function(e) {
                e.preventDefault();
                $.post($(this).attr('action'), $(this).serialize(), function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error saving coverage item');
                    }
                }, 'json');
            });
        });
    </script>
</body>
</html>
