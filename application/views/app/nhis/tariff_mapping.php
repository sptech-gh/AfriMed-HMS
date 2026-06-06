<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Hebrew Medical Center — NHIS Tariffs</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/datatables/dataTables.bootstrap.css" rel="stylesheet" type="text/css" />
</head>
<body class="skin-blue">
    <?php require_once(APPPATH.'views/include/header.php');?>
    <div class="wrapper row-offcanvas row-offcanvas-left">
        <?php require_once(APPPATH.'views/include/sidebar.php');?>
        <aside class="right-side">
            <section class="content-header">
                <h1><i class="fa fa-tags"></i> NHIS Tariffs <small>Ghana NHIS service pricing</small></h1>
                <ol class="breadcrumb">
                    <li><a href="<?php echo base_url()?>app/nhis_claims/claimit"><i class="fa fa-cloud-upload"></i> Claim-IT</a></li>
                    <li class="active">Tariff Mapping</li>
                </ol>
            </section>

            <section class="content">
                <?php if(isset($message) && $message){ echo $message; } ?>

                <div class="box box-info">
                    <div class="box-header with-border">
                        <h3 class="box-title">Import Service Tariffs CSV</h3>
                    </div>
                    <div class="box-body">
                        <form method="POST" action="<?php echo base_url()?>app/nhis_claims/import_service_tariffs_csv" enctype="multipart/form-data" class="form-inline">
                            <div class="form-group" style="margin-right:10px;">
                                <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                            </div>
                            <div class="form-group" style="margin-right:10px;">
                                <input type="text" name="version_label" class="form-control" placeholder="Version label">
                            </div>
                            <div class="form-group" style="margin-right:10px;">
                                <input type="date" name="effective_date" class="form-control" placeholder="Effective date">
                            </div>
                            <div class="form-group" style="margin-right:10px;">
                                <input type="text" name="source_name" class="form-control" placeholder="Source name">
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="fa fa-upload"></i> Import</button>
                        </form>
                    </div>
                </div>

                <!-- Category Filter -->
                <div class="box box-solid">
                    <div class="box-body">
                        <div class="btn-group">
                            <button type="button" class="btn btn-default category-filter active" data-category="">All Categories</button>
                            <?php if(!empty($categories)): foreach($categories as $cat): 
                                $catName = is_array($cat) ? ($cat['category'] ?? '') : (is_object($cat) ? ($cat->category ?? '') : $cat);
                            ?>
                            <button type="button" class="btn btn-default category-filter" data-category="<?php echo htmlspecialchars($catName); ?>"><?php echo htmlspecialchars($catName); ?></button>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>
                </div>

                <div class="box box-success">
                    <div class="box-header with-border">
                        <h3 class="box-title">NHIS Tariff Schedule</h3>
                        <span class="badge bg-green pull-right" id="tariffCount"><?php echo count($tariffs ?? []); ?> tariffs</span>
                    </div>
                    <div class="box-body">
                        <table class="table table-bordered table-striped" id="tariffTable">
                            <thead>
                                <tr>
                                    <th>Service Code</th>
                                    <th>Service Name</th>
                                    <th>Category</th>
                                    <th class="text-right">Tariff (GHS)</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(!empty($tariffs)): foreach($tariffs as $t): ?>
                                <tr data-category="<?php echo htmlspecialchars($t->category ?? ''); ?>">
                                    <td><code><?php echo htmlspecialchars($t->service_code); ?></code></td>
                                    <td><?php echo htmlspecialchars($t->service_name); ?></td>
                                    <td><span class="label label-info"><?php echo htmlspecialchars($t->category ?? '-'); ?></span></td>
                                    <td class="text-right"><strong><?php echo number_format(isset($t->tariff) ? $t->tariff : (isset($t->tariff_amount) ? $t->tariff_amount : 0), 2); ?></strong></td>
                                    <td>
                                        <?php if(isset($t->is_active) && !$t->is_active): ?>
                                        <span class="label label-default">Inactive</span>
                                        <?php else: ?>
                                        <span class="label label-success">Active</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; else: ?>
                                <tr><td colspan="5" class="text-center text-muted">No tariffs found</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </section>
        </aside>
    </div>

    <script src="<?php echo base_url();?>public/js/jquery.min.js"></script>
    <script src="<?php echo base_url();?>public/js/bootstrap.min.js"></script>
    <script src="<?php echo base_url();?>public/js/AdminLTE/app.js"></script>
    <script src="<?php echo base_url();?>public/js/plugins/datatables/jquery.dataTables.js"></script>
    <script src="<?php echo base_url();?>public/js/plugins/datatables/dataTables.bootstrap.js"></script>
    <script>
    $(function(){
        var table = $('#tariffTable').DataTable({ "pageLength": 50, "order": [[2, "asc"], [0, "asc"]] });
        
        $('.category-filter').click(function(){
            $('.category-filter').removeClass('active');
            $(this).addClass('active');
            var cat = $(this).data('category');
            if(cat) {
                table.column(2).search(cat).draw();
            } else {
                table.column(2).search('').draw();
            }
        });
    });
    </script>
</body>
</html>
