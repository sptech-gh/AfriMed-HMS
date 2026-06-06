<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Price List Management - HMS</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
    <style>
        .price-input { width: 100px; text-align: right; }
        .summary-box { border-left: 3px solid #3c8dbc; padding: 10px 15px; margin-bottom: 10px; background: #f9f9f9; }
        .summary-box .number { font-size: 24px; font-weight: bold; color: #3c8dbc; }
        .nhis-badge { background: #00a65a; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px; }
        .zero-price { background-color: #fff3cd !important; }
        .table-fixed-header { position: sticky; top: 0; background: #f4f4f4; z-index: 10; }
        .category-badge { padding: 3px 8px; border-radius: 3px; font-size: 11px; }
        .cat-services { background: #d9edf7; color: #31708f; }
        .cat-medicines { background: #dff0d8; color: #3c763d; }
        .cat-laboratory { background: #fcf8e3; color: #8a6d3b; }
        .cat-sonography { background: #f2dede; color: #a94442; }
        .cat-rooms { background: #e8e8e8; color: #555; }
        .modified { background-color: #d4edda !important; }
        .filter-box { background: #f9f9f9; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
    </style>
</head>
<body class="skin-blue">
    <?php require_once(APPPATH.'views/include/header.php');?>
    
    <div class="wrapper row-offcanvas row-offcanvas-left">
        <?php require_once(APPPATH.'views/include/sidebar.php');?>
        
        <aside class="right-side">
            <section class="content-header">
                <h1>
                    <i class="fa fa-tags"></i> Price List Management
                    <small>Centralized billing prices</small>
                </h1>
                <ol class="breadcrumb">
                    <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                    <li><a href="#">Admin</a></li>
                    <li class="active">Price List</li>
                </ol>
            </section>
            
            <section class="content">
                <?php if (isset($message) && $message): ?>
                    <?php echo $message; ?>
                <?php endif; ?>
                
                <!-- Summary Cards -->
                <div class="row">
                    <div class="col-md-2 col-sm-4 col-xs-6">
                        <div class="summary-box">
                            <div class="number"><?php echo number_format($summary['total_items']); ?></div>
                            <div class="text-muted">Total Items</div>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-4 col-xs-6">
                        <div class="summary-box">
                            <div class="number"><?php echo number_format($summary['services']); ?></div>
                            <div class="text-muted">Services</div>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-4 col-xs-6">
                        <div class="summary-box">
                            <div class="number"><?php echo number_format($summary['medicines']); ?></div>
                            <div class="text-muted">Medicines</div>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-4 col-xs-6">
                        <div class="summary-box">
                            <div class="number"><?php echo number_format($summary['laboratory']); ?></div>
                            <div class="text-muted">Lab Tests</div>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-4 col-xs-6">
                        <div class="summary-box" style="border-left-color: #00a65a;">
                            <div class="number" style="color: #00a65a;"><?php echo number_format($summary['nhis_covered']); ?></div>
                            <div class="text-muted">NHIS Covered</div>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-4 col-xs-6">
                        <div class="summary-box" style="border-left-color: #f39c12;">
                            <div class="number" style="color: #f39c12;"><?php echo number_format($summary['zero_price']); ?></div>
                            <div class="text-muted">Zero Price</div>
                        </div>
                    </div>
                </div>
                
                <!-- Filter & Actions -->
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-filter"></i> Filter & Actions</h3>
                    </div>
                    <div class="box-body">
                        <form method="get" action="<?php echo base_url(); ?>app/price_list" class="form-inline">
                            <div class="form-group" style="margin-right: 15px;">
                                <label style="margin-right: 5px;">Category:</label>
                                <select name="category" class="form-control input-sm" onchange="this.form.submit()">
                                    <?php foreach ($categories as $key => $label): ?>
                                        <option value="<?php echo $key; ?>" <?php echo ($category == $key) ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group" style="margin-right: 15px;">
                                <label style="margin-right: 5px;">Search:</label>
                                <input type="text" name="search" class="form-control input-sm" value="<?php echo htmlspecialchars($search); ?>" placeholder="Item name...">
                            </div>
                            <button type="submit" class="btn btn-sm btn-primary"><i class="fa fa-search"></i> Filter</button>
                            <a href="<?php echo base_url(); ?>app/price_list" class="btn btn-sm btn-default"><i class="fa fa-refresh"></i> Reset</a>
                            
                            <div class="pull-right">
                                <a href="<?php echo base_url(); ?>app/price_list/export?category=<?php echo $category; ?>" class="btn btn-sm btn-success"><i class="fa fa-download"></i> Export CSV</a>
                                <a href="<?php echo base_url(); ?>app/price_list/adjust" class="btn btn-sm btn-warning"><i class="fa fa-percent"></i> Bulk Adjust</a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Price List Table -->
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-list"></i> Price List (<?php echo count($prices); ?> items)</h3>
                        <div class="box-tools pull-right">
                            <button type="button" class="btn btn-sm btn-success" id="saveChanges" disabled>
                                <i class="fa fa-save"></i> Save Changes (<span id="changeCount">0</span>)
                            </button>
                        </div>
                    </div>
                    <div class="box-body table-responsive" style="max-height: 600px; overflow-y: auto;">
                        <form id="priceForm" method="post" action="<?php echo base_url(); ?>app/price_list/bulk_update">
                            <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                            
                            <table class="table table-bordered table-striped table-hover" id="priceTable">
                                <thead class="table-fixed-header">
                                    <tr>
                                        <th width="5%">#</th>
                                        <th width="15%">Category</th>
                                        <th width="30%">Item Name</th>
                                        <th width="15%">Description</th>
                                        <th width="12%" class="text-right">Cash Price (GHS)</th>
                                        <th width="12%" class="text-right">NHIS Price (GHS)</th>
                                        <th width="6%" class="text-center">NHIS</th>
                                        <th width="5%">Edit</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($prices)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center text-muted">No items found matching your criteria.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php $i = 1; foreach ($prices as $item): ?>
                                            <?php 
                                                $isZeroPrice = ($item->cash_price == 0);
                                                $catClass = 'cat-services';
                                                switch ($item->item_type) {
                                                    case 'medicine': $catClass = 'cat-medicines'; break;
                                                    case 'room': $catClass = 'cat-rooms'; break;
                                                    case 'sonography': $catClass = 'cat-sonography'; break;
                                                    case 'lab_test': $catClass = 'cat-laboratory'; break;
                                                }
                                            ?>
                                            <tr class="<?php echo $isZeroPrice ? 'zero-price' : ''; ?>" data-item-type="<?php echo $item->item_type; ?>" data-item-id="<?php echo $item->item_id; ?>">
                                                <td><?php echo $i++; ?></td>
                                                <td><span class="category-badge <?php echo $catClass; ?>"><?php echo htmlspecialchars($item->category_name); ?></span></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($item->item_name); ?></strong>
                                                    <?php if ($item->is_nhis_covered): ?>
                                                        <span class="nhis-badge">NHIS</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><small class="text-muted"><?php echo htmlspecialchars($item->description ?: '-'); ?></small></td>
                                                <td class="text-right">
                                                    <span class="display-price"><?php echo number_format($item->cash_price, 2); ?></span>
                                                    <input type="number" step="0.01" min="0" class="form-control input-sm price-input edit-price cash-price" 
                                                           value="<?php echo $item->cash_price; ?>" 
                                                           data-original="<?php echo $item->cash_price; ?>"
                                                           style="display:none;">
                                                </td>
                                                <td class="text-right">
                                                    <span class="display-price"><?php echo number_format($item->nhis_price, 2); ?></span>
                                                    <input type="number" step="0.01" min="0" class="form-control input-sm price-input edit-price nhis-price" 
                                                           value="<?php echo $item->nhis_price; ?>" 
                                                           data-original="<?php echo $item->nhis_price; ?>"
                                                           style="display:none;">
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($item->is_nhis_covered): ?>
                                                        <i class="fa fa-check-circle text-success"></i>
                                                    <?php else: ?>
                                                        <i class="fa fa-minus-circle text-muted"></i>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-xs btn-info edit-btn" title="Edit prices">
                                                        <i class="fa fa-pencil"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-xs btn-success save-btn" title="Confirm" style="display:none;">
                                                        <i class="fa fa-check"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-xs btn-default cancel-btn" title="Cancel" style="display:none;">
                                                        <i class="fa fa-times"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                            
                            <!-- Hidden inputs for bulk update -->
                            <div id="hiddenUpdates"></div>
                        </form>
                    </div>
                    <div class="box-footer">
                        <div class="row">
                            <div class="col-md-6">
                                <span class="text-muted">Showing <?php echo count($prices); ?> items</span>
                            </div>
                            <div class="col-md-6 text-right">
                                <button type="button" class="btn btn-success" id="saveAllChanges" disabled>
                                    <i class="fa fa-save"></i> Save All Changes
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Links -->
                <div class="box box-default">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-link"></i> Direct Edit Links</h3>
                    </div>
                    <div class="box-body">
                        <div class="row">
                            <div class="col-md-4">
                                <a href="<?php echo base_url(); ?>app/particular_bill" class="btn btn-block btn-default">
                                    <i class="fa fa-list-alt"></i> Service Charges (Detailed)
                                </a>
                            </div>
                            <div class="col-md-4">
                                <a href="<?php echo base_url(); ?>app/drug_name" class="btn btn-block btn-default">
                                    <i class="fa fa-capsules"></i> Drug Names (Detailed)
                                </a>
                            </div>
                            <div class="col-md-4">
                                <a href="<?php echo base_url(); ?>app/room_master" class="btn btn-block btn-default">
                                    <i class="fa fa-bed"></i> Room Rates (Detailed)
                                </a>
                            </div>
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
        var pendingChanges = {};
        
        // Edit button click
        $(document).on('click', '.edit-btn', function() {
            var $row = $(this).closest('tr');
            $row.find('.display-price').hide();
            $row.find('.edit-price').show().focus();
            $row.find('.edit-btn').hide();
            $row.find('.save-btn, .cancel-btn').show();
        });
        
        // Cancel button click
        $(document).on('click', '.cancel-btn', function() {
            var $row = $(this).closest('tr');
            $row.find('.edit-price').each(function() {
                $(this).val($(this).data('original'));
            });
            $row.find('.display-price').show();
            $row.find('.edit-price').hide();
            $row.find('.edit-btn').show();
            $row.find('.save-btn, .cancel-btn').hide();
            $row.removeClass('modified');
            
            // Remove from pending changes
            var key = $row.data('item-type') + '_' + $row.data('item-id');
            delete pendingChanges[key];
            updateChangeCount();
        });
        
        // Save button click (per row)
        $(document).on('click', '.save-btn', function() {
            var $row = $(this).closest('tr');
            var itemType = $row.data('item-type');
            var itemId = $row.data('item-id');
            var cashPrice = parseFloat($row.find('.cash-price').val()) || 0;
            var nhisPrice = parseFloat($row.find('.nhis-price').val()) || 0;
            
            // Update display
            $row.find('.display-price').eq(0).text(cashPrice.toFixed(2));
            $row.find('.display-price').eq(1).text(nhisPrice.toFixed(2));
            
            // Store change
            var key = itemType + '_' + itemId;
            pendingChanges[key] = {
                item_type: itemType,
                item_id: itemId,
                cash_price: cashPrice,
                nhis_price: nhisPrice
            };
            
            // Update UI
            $row.find('.display-price').show();
            $row.find('.edit-price').hide();
            $row.find('.edit-btn').show();
            $row.find('.save-btn, .cancel-btn').hide();
            $row.addClass('modified');
            
            // Update data-original
            $row.find('.cash-price').data('original', cashPrice);
            $row.find('.nhis-price').data('original', nhisPrice);
            
            updateChangeCount();
        });
        
        // Update change count
        function updateChangeCount() {
            var count = Object.keys(pendingChanges).length;
            $('#changeCount').text(count);
            $('#saveChanges, #saveAllChanges').prop('disabled', count === 0);
        }
        
        // Save all changes
        $('#saveChanges, #saveAllChanges').click(function() {
            if (Object.keys(pendingChanges).length === 0) return;
            
            var $form = $('#priceForm');
            var $hidden = $('#hiddenUpdates');
            $hidden.empty();
            
            var i = 0;
            $.each(pendingChanges, function(key, data) {
                $hidden.append('<input type="hidden" name="updates[' + i + '][item_type]" value="' + data.item_type + '">');
                $hidden.append('<input type="hidden" name="updates[' + i + '][item_id]" value="' + data.item_id + '">');
                $hidden.append('<input type="hidden" name="updates[' + i + '][cash_price]" value="' + data.cash_price + '">');
                $hidden.append('<input type="hidden" name="updates[' + i + '][nhis_price]" value="' + data.nhis_price + '">');
                i++;
            });
            
            $form.submit();
        });
    });
    </script>
</body>
</html>
