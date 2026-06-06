<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Hebrew Medical Center — NHIS Reference Data</title>
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
                <h1><i class="fa fa-database"></i> NHIS Reference Data <small>manual upload + strict versioning</small></h1>
                <ol class="breadcrumb">
                    <li><a href="<?php echo base_url()?>app/nhis"><i class="fa fa-shield"></i> NHIS</a></li>
                    <li class="active">Reference Data</li>
                </ol>
            </section>

            <section class="content">
                <?php if(isset($message) && $message){ echo $message; } ?>

                <div class="box box-warning">
                    <div class="box-header with-border">
                        <h3 class="box-title">Upload Rules</h3>
                    </div>
                    <div class="box-body">
                        <div class="alert alert-info" style="margin-bottom:0;">
                            <strong>Required columns:</strong> <code>nhis_code</code>, <code>description</code>. Optional: <code>tariff_amount</code> (or <code>tariff</code>).<br>
                            Imports are <strong>transactional</strong> and will <strong>fail fast</strong> on any invalid row. Existing rows are never overwritten; older active rows are marked inactive.
                        </div>
                    </div>
                </div>

                <div class="box box-success">
                    <div class="box-header with-border">
                        <h3 class="box-title">Datasets</h3>
                    </div>
                    <div class="box-body">
                        <table class="table table-bordered table-striped" id="nhisRefTable">
                            <thead>
                                <tr>
                                    <th>Dataset</th>
                                    <th>Active Version</th>
                                    <th>Effective Date</th>
                                    <th class="text-right">Active Rows</th>
                                    <th class="text-right">Total Rows</th>
                                    <th>Import CSV</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(!empty($datasets)): foreach($datasets as $d): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($d['label']); ?></strong><br><small class="text-muted"><?php echo htmlspecialchars($d['table']); ?></small></td>
                                    <td><?php echo htmlspecialchars($d['active_version'] ? $d['active_version'] : '-'); ?></td>
                                    <td><?php echo htmlspecialchars($d['active_effective_date'] ? $d['active_effective_date'] : '-'); ?></td>
                                    <td class="text-right"><?php echo number_format((int)$d['active_count']); ?></td>
                                    <td class="text-right"><?php echo number_format((int)$d['total_count']); ?></td>
                                    <td>
                                        <form method="POST" action="<?php echo base_url()?>app/nhis_reference/import/<?php echo urlencode($d['type']); ?>" enctype="multipart/form-data" class="form-inline">
                                            <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                                            <div class="form-group" style="margin-right:10px;">
                                                <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                                            </div>
                                            <div class="form-group" style="margin-right:10px;">
                                                <input type="text" name="version" class="form-control" placeholder="Version" required>
                                            </div>
                                            <button type="submit" class="btn btn-primary"><i class="fa fa-upload"></i> Import</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; else: ?>
                                <tr><td colspan="6" class="text-center text-muted">No datasets found</td></tr>
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
        $('#nhisRefTable').DataTable({ "pageLength": 25, "order": [[0, "asc"]] });
    });
    </script>
</body>
</html>
