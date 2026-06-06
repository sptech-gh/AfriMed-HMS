<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Hebrew Medical Center — ICD-10 Codes</title>
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
                <h1><i class="fa fa-stethoscope"></i> ICD-10 Codes <small>Diagnosis coding for NHIS claims</small></h1>
                <ol class="breadcrumb">
                    <li><a href="<?php echo base_url()?>app/nhis_claims/claimit"><i class="fa fa-cloud-upload"></i> Claim-IT</a></li>
                    <li class="active">ICD-10 Codes</li>
                </ol>
            </section>

            <section class="content">
                <?php if(isset($message) && $message){ echo $message; } ?>

                <div class="box box-info">
                    <div class="box-header with-border">
                        <h3 class="box-title">Import ICD-10 CSV</h3>
                    </div>
                    <div class="box-body">
                        <form method="POST" action="<?php echo base_url()?>app/nhis_claims/import_icd10_csv" enctype="multipart/form-data" class="form-inline">
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

                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">ICD-10 Code Reference</h3>
                        <span class="badge bg-blue pull-right"><?php echo count($codes ?? []); ?> codes</span>
                    </div>
                    <div class="box-body">
                        <table class="table table-bordered table-striped" id="icd10Table">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Description</th>
                                    <th>Category</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(!empty($codes)): foreach($codes as $c): ?>
                                <tr>
                                    <td><code><?php echo htmlspecialchars($c->code); ?></code></td>
                                    <td><?php echo htmlspecialchars($c->description); ?></td>
                                    <td><?php echo htmlspecialchars($c->category ?? '-'); ?></td>
                                    <td>
                                        <?php if(isset($c->is_active) && !$c->is_active): ?>
                                        <span class="label label-default">Inactive</span>
                                        <?php else: ?>
                                        <span class="label label-success">Active</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; else: ?>
                                <tr><td colspan="4" class="text-center text-muted">No ICD-10 codes found</td></tr>
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
        $('#icd10Table').DataTable({ "pageLength": 50, "order": [[0, "asc"]] });
    });
    </script>
</body>
</html>
