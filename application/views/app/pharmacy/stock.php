<!DOCTYPE html>
<html>
    <head>
<head>

        <meta charset="UTF-8">
        <title>Hebrew Medical Center</title>
        <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">

        <link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
        <link href="<?php echo base_url();?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
        <link href="<?php echo base_url();?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
        <link href="<?php echo base_url();?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />

        <style>
            .badge-status { font-size: 12px; padding: 6px 10px; }
            .table td { vertical-align: middle !important; }
            .stock-actions form { display: inline-block; margin: 0 2px; }
        </style>

        <!--[if lt IE 9]>
          <script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>
          <script src="https://oss.maxcdn.com/libs/respond.js/1.3.0/respond.min.js"></script>
        <![endif]-->

    </head>
    <body class="skin-blue">
        <?php require_once(APPPATH.'views/include/header.php');?>

        <div class="wrapper row-offcanvas row-offcanvas-left">

            <?php require_once(APPPATH.'views/include/sidebar.php');?>

            <aside class="right-side">
                <section class="content-header">
                    <h1>Stock Management</h1>
                    <ol class="breadcrumb">
                        <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                        <li><a href="<?php echo base_url()?>app/pharmacy">Pharmacy</a></li>
                        <li class="active">Stock Management</li>
                    </ol>
                </section>

                <section class="content">

                    <?php echo isset($message) ? $message : ''; ?>

                    <!-- Summary -->
                    <div class="row">
                        <div class="col-md-4">
                            <div class="small-box bg-aqua">
                                <div class="inner"><h3><?php echo isset($total_count) ? (int)$total_count : 0; ?></h3><p>Total Drug Items</p></div>
                                <div class="icon"><i class="fa fa-cubes"></i></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="small-box bg-red">
                                <div class="inner"><h3><?php echo isset($low_stock_count) ? (int)$low_stock_count : 0; ?></h3><p>Low / Out of Stock</p></div>
                                <div class="icon"><i class="fa fa-exclamation-triangle"></i></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="small-box bg-green">
                                <div class="inner"><h3>&nbsp;</h3><p>&nbsp;</p></div>
                                <div class="icon"><i class="fa fa-medkit"></i></div>
                                <a href="<?php echo base_url(); ?>app/pharmacy" class="small-box-footer"><i class="fa fa-arrow-circle-left"></i> Back to Worklist</a>
                            </div>
                        </div>
                    </div>

                    <!-- Filters -->
                    <div class="box box-primary">
                        <div class="box-header">
                            <h3 class="box-title">Filters</h3>
                        </div>
                        <div class="box-body">
                            <form method="get" action="<?php echo base_url(); ?>app/pharmacy/stock">
                                <div class="row">
                                    <div class="col-md-5">
                                        <div class="form-group">
                                            <label>Search Drug</label>
                                            <input type="text" name="search" class="form-control input-sm" value="<?php echo isset($filters['search']) ? htmlspecialchars($filters['search']) : ''; ?>" placeholder="Drug name or ID">
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label>Show Low Stock Only</label>
                                            <select name="show_low" class="form-control input-sm">
                                                <option value="0" <?php echo (isset($filters['show_low']) && !$filters['show_low']) ? 'selected' : ''; ?>>All</option>
                                                <option value="1" <?php echo (isset($filters['show_low']) && $filters['show_low']) ? 'selected' : ''; ?>>Low Stock Only</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label>Limit</label>
                                            <input type="number" name="limit" class="form-control input-sm" value="<?php echo isset($filters['limit']) && (int)$filters['limit'] > 0 ? (int)$filters['limit'] : 50; ?>" min="1" max="500">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group" style="margin-top: 25px;">
                                            <button class="btn btn-primary btn-sm" type="submit"><i class="fa fa-filter"></i> Apply</button>
                                            <a class="btn btn-default btn-sm" href="<?php echo base_url(); ?>app/pharmacy/stock"><i class="fa fa-refresh"></i> Reset</a>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Stock Table -->
                    <div class="box box-primary">
                        <div class="box-header">
                            <h3 class="box-title">Drug Stock</h3>
                        </div>
                        <div class="box-body table-responsive">

                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Drug Name</th>
                                        <th>Current Stock</th>
                                        <th>Reorder Level</th>
                                        <th>Price</th>
                                        <th>NHIS</th>
                                        <th>Status</th>
                                        <th style="width: 280px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!isset($stock_list) || !is_array($stock_list) || count($stock_list) === 0) { ?>
                                        <tr>
                                            <td colspan="8"><em>No drugs found.</em></td>
                                        </tr>
                                    <?php } else { ?>
                                        <?php foreach ($stock_list as $drug) { ?>
                                            <?php
                                                $drugId = (int)$drug->drug_id;
                                                $stock = (float)$drug->nStock;
                                                $reorder = (float)$drug->re_order_level;
                                                $isLow = ($stock <= $reorder);
                                                $isOut = ($stock <= 0);
                                                $nhis = isset($drug->is_nhis_covered) ? (int)$drug->is_nhis_covered : 0;
                                            ?>
                                            <tr class="<?php echo $isOut ? 'danger' : ($isLow ? 'warning' : ''); ?>">
                                                <td><?php echo $drugId; ?></td>
                                                <td><?php echo htmlspecialchars((string)$drug->drug_name); ?></td>
                                                <td>
                                                    <strong class="<?php echo $isOut ? 'text-danger' : ($isLow ? 'text-warning' : 'text-success'); ?>">
                                                        <?php echo htmlspecialchars((string)$stock); ?>
                                                    </strong>
                                                </td>
                                                <td><?php echo htmlspecialchars((string)$reorder); ?></td>
                                                <td><?php echo htmlspecialchars(number_format((float)$drug->nPrice, 2)); ?></td>
                                                <td>
                                                    <?php if ($nhis) { ?>
                                                        <span class="label label-success" title="NHIS Covered"><i class="fa fa-shield"></i> Yes</span>
                                                        <br><small>GHS <?php echo number_format((float)$drug->nhis_price, 2); ?></small>
                                                    <?php } else { ?>
                                                        <span class="label label-default">No</span>
                                                    <?php } ?>
                                                </td>
                                                <td>
                                                    <?php if ($isOut) { ?>
                                                        <span class="label label-danger"><i class="fa fa-ban"></i> OUT OF STOCK</span>
                                                    <?php } elseif ($isLow) { ?>
                                                        <span class="label label-warning"><i class="fa fa-exclamation-triangle"></i> LOW</span>
                                                    <?php } else { ?>
                                                        <span class="label label-success"><i class="fa fa-check"></i> OK</span>
                                                    <?php } ?>
                                                </td>
                                                <td class="stock-actions">
                                                    <a class="btn btn-info btn-xs" href="<?php echo base_url(); ?>app/pharmacy/stock_history/<?php echo $drugId; ?>"><i class="fa fa-history"></i> History</a>

                                                    <button type="button" class="btn btn-success btn-xs" data-toggle="modal" data-target="#restockModal<?php echo $drugId; ?>"><i class="fa fa-plus"></i> Restock</button>

                                                    <button type="button" class="btn btn-default btn-xs" data-toggle="modal" data-target="#batchRestockModal<?php echo $drugId; ?>"><i class="fa fa-archive"></i> Batch Restock</button>

                                                    <button type="button" class="btn btn-warning btn-xs" data-toggle="modal" data-target="#adjustModal<?php echo $drugId; ?>"><i class="fa fa-pencil"></i> Adjust</button>

                                                    <!-- Batch Restock Modal -->
                                                    <div class="modal fade" id="batchRestockModal<?php echo $drugId; ?>" tabindex="-1" role="dialog" aria-hidden="true">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <form method="post" action="<?php echo base_url(); ?>app/pharmacy/batch_restock">
                                                                    <div class="modal-header">
                                                                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                                                                        <h4 class="modal-title">Batch Restock: <?php echo htmlspecialchars((string)$drug->drug_name); ?></h4>
                                                                    </div>
                                                                    <div class="modal-body">
                                                                        <input type="hidden" name="medication_id" value="<?php echo $drugId; ?>">
                                                                        <div class="form-group">
                                                                            <label>Batch Number <span class="text-danger">*</span></label>
                                                                            <input type="text" class="form-control" name="batch_number" required placeholder="e.g. BN-2025-001">
                                                                        </div>
                                                                        <div class="form-group">
                                                                            <label>Quantity <span class="text-danger">*</span></label>
                                                                            <input type="number" step="0.01" min="0.01" class="form-control" name="quantity" required>
                                                                        </div>
                                                                        <div class="form-group">
                                                                            <label>Expiry Date</label>
                                                                            <input type="date" class="form-control" name="expiry_date">
                                                                        </div>
                                                                        <div class="row">
                                                                            <div class="col-md-6">
                                                                                <div class="form-group">
                                                                                    <label>Unit Cost</label>
                                                                                    <input type="number" step="0.01" min="0" class="form-control" name="unit_cost" value="0">
                                                                                </div>
                                                                            </div>
                                                                            <div class="col-md-6">
                                                                                <div class="form-group">
                                                                                    <label>Selling Price</label>
                                                                                    <input type="number" step="0.01" min="0" class="form-control" name="selling_price" value="<?php echo htmlspecialchars(number_format((float)$drug->nPrice, 2, '.', '')); ?>">
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="form-group">
                                                                            <label>Supplier</label>
                                                                            <input type="text" class="form-control" name="supplier" placeholder="Supplier name">
                                                                        </div>
                                                                    </div>
                                                                    <div class="modal-footer">
                                                                        <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                                                                        <button type="submit" class="btn btn-success">Add Batch</button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Restock Modal -->
                                                    <div class="modal fade" id="restockModal<?php echo $drugId; ?>" tabindex="-1" role="dialog" aria-hidden="true">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <form method="post" action="<?php echo base_url(); ?>app/pharmacy/adjust_stock_action">
                                                                    <div class="modal-header">
                                                                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                                                                        <h4 class="modal-title">Restock: <?php echo htmlspecialchars((string)$drug->drug_name); ?></h4>
                                                                    </div>
                                                                    <div class="modal-body">
                                                                        <input type="hidden" name="drug_id" value="<?php echo $drugId; ?>">
                                                                        <p>Current stock: <strong><?php echo htmlspecialchars((string)$stock); ?></strong></p>
                                                                        <div class="form-group">
                                                                            <label>Quantity to Add</label>
                                                                            <input type="number" step="0.01" min="0.01" class="form-control" name="qty_change" required>
                                                                        </div>
                                                                        <div class="form-group">
                                                                            <label>Reason</label>
                                                                            <textarea class="form-control" name="reason" rows="2" required placeholder="e.g. New supply received"></textarea>
                                                                        </div>
                                                                    </div>
                                                                    <div class="modal-footer">
                                                                        <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                                                                        <button type="submit" class="btn btn-success">Restock</button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Adjust (Write-off) Modal -->
                                                    <div class="modal fade" id="adjustModal<?php echo $drugId; ?>" tabindex="-1" role="dialog" aria-hidden="true">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <form method="post" action="<?php echo base_url(); ?>app/pharmacy/adjust_stock_action">
                                                                    <div class="modal-header">
                                                                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                                                                        <h4 class="modal-title">Adjust Stock: <?php echo htmlspecialchars((string)$drug->drug_name); ?></h4>
                                                                    </div>
                                                                    <div class="modal-body">
                                                                        <input type="hidden" name="drug_id" value="<?php echo $drugId; ?>">
                                                                        <p>Current stock: <strong><?php echo htmlspecialchars((string)$stock); ?></strong></p>
                                                                        <div class="form-group">
                                                                            <label>Quantity Change <small class="text-muted">(negative to remove)</small></label>
                                                                            <input type="number" step="0.01" class="form-control" name="qty_change" required>
                                                                        </div>
                                                                        <div class="form-group">
                                                                            <label>Reason</label>
                                                                            <textarea class="form-control" name="reason" rows="2" required placeholder="e.g. Expired, Damaged, Correction"></textarea>
                                                                        </div>
                                                                    </div>
                                                                    <div class="modal-footer">
                                                                        <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                                                                        <button type="submit" class="btn btn-warning">Adjust</button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php } ?>
                                    <?php } ?>
                                </tbody>
                            </table>

                        </div>
                    </div>

                </section>

            </aside>
        </div>

         <script src="<?php echo base_url();?>public/js/jquery.min.js"></script>
         <script src="<?php echo base_url();?>public/js/bootstrap.min.js" type="text/javascript"></script>
         <script src="<?php echo base_url();?>public/js/AdminLTE/app.js" type="text/javascript"></script>

    </body>
</html>
