<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo $title; ?> | HMS</title>
    <meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>
    <link href="<?php echo base_url() ?>public/bootstrap/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url() ?>public/font-awesome/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url() ?>public/dist/css/AdminLTE.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url() ?>public/dist/css/skins/skin-blue.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url() ?>public/plugins/datatables/dataTables.bootstrap.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url() ?>public/plugins/datepicker/datepicker3.css" rel="stylesheet" type="text/css" />
</head>
<body class="skin-blue sidebar-mini">
<?php require_once(APPPATH . 'views/include/header.php'); ?>

<div class="wrapper row-offcanvas row-offcanvas-left">
    <?php require_once(APPPATH . 'views/include/sidebar.php'); ?>
    
    <aside class="right-side">
        <section class="content-header">
            <h1><i class="fa fa-id-card"></i> <?php echo $title; ?></h1>
            <ol class="breadcrumb">
                <li><a href="<?php echo base_url() ?>app/dashboard"><i class="fa fa-dashboard"></i> Dashboard</a></li>
                <li><a href="<?php echo base_url() ?>app/result_approval">Result Approval</a></li>
                <li class="active">Credentials</li>
            </ol>
        </section>

        <section class="content">
            <?php if ($this->session->flashdata('success')): ?>
            <div class="alert alert-success alert-dismissible">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                <?php echo $this->session->flashdata('success'); ?>
            </div>
            <?php endif; ?>
            <?php if ($this->session->flashdata('error')): ?>
            <div class="alert alert-danger alert-dismissible">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                <?php echo $this->session->flashdata('error'); ?>
            </div>
            <?php endif; ?>

            <div class="row">
                <!-- Grant Credential Form -->
                <div class="col-md-4">
                    <div class="box box-primary">
                        <div class="box-header with-border">
                            <h3 class="box-title"><i class="fa fa-plus"></i> Grant Credential</h3>
                        </div>
                        <form action="<?php echo base_url() ?>app/result_approval/grant_credential" method="post">
                            <div class="box-body">
                                <div class="form-group">
                                    <label>User <span class="text-red">*</span></label>
                                    <select name="user_id" class="form-control" required>
                                        <option value="">-- Select User --</option>
                                        <?php foreach ($users as $u): ?>
                                        <option value="<?php echo $u->user_id; ?>">
                                            <?php echo htmlspecialchars($u->username . ' - ' . $u->firstname . ' ' . $u->lastname); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Diagnostic Type <span class="text-red">*</span></label>
                                    <select name="diagnostic_type" class="form-control" required>
                                        <option value="ALL">All Types</option>
                                        <option value="LAB">Laboratory</option>
                                        <option value="RADIOLOGY">Radiology</option>
                                        <option value="SONOGRAPHY">Sonography</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Verification Permissions</label>
                                    <div class="checkbox">
                                        <label><input type="checkbox" name="can_level_1" value="1"> Can Verify Level 1</label>
                                    </div>
                                    <div class="checkbox">
                                        <label><input type="checkbox" name="can_level_2" value="1"> Can Verify Level 2</label>
                                    </div>
                                    <div class="checkbox">
                                        <label><input type="checkbox" name="can_critical" value="1"> Can Verify Critical Results</label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Certification Number</label>
                                    <input type="text" name="certification_number" class="form-control" placeholder="e.g., LAB-2024-001">
                                </div>
                                <div class="form-group">
                                    <label>Certification Expiry</label>
                                    <input type="text" name="certification_expiry" class="form-control datepicker" placeholder="YYYY-MM-DD">
                                </div>
                                <div class="form-group">
                                    <label>Experience Start Date</label>
                                    <input type="text" name="experience_start_date" class="form-control datepicker" placeholder="YYYY-MM-DD">
                                </div>
                            </div>
                            <div class="box-footer">
                                <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Grant Credential</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Credentials List -->
                <div class="col-md-8">
                    <div class="box box-info">
                        <div class="box-header with-border">
                            <h3 class="box-title"><i class="fa fa-list"></i> Active Credentials</h3>
                        </div>
                        <div class="box-body">
                            <?php if (empty($credentials)): ?>
                            <div class="alert alert-info">No credentials configured yet.</div>
                            <?php else: ?>
                            <table id="credentialsTable" class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Type</th>
                                        <th>Permissions</th>
                                        <th>Certification</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($credentials as $c): ?>
                                    <tr>
                                        <td>
                                            <?php echo htmlspecialchars($c->username); ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($c->firstname . ' ' . $c->lastname); ?></small>
                                        </td>
                                        <td>
                                            <span class="label label-<?php echo $c->diagnostic_type == 'ALL' ? 'primary' : 'info'; ?>">
                                                <?php echo $c->diagnostic_type; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($c->can_verify_level_1): ?>
                                            <span class="label label-success">L1</span>
                                            <?php endif; ?>
                                            <?php if ($c->can_verify_level_2): ?>
                                            <span class="label label-warning">L2</span>
                                            <?php endif; ?>
                                            <?php if ($c->can_verify_critical): ?>
                                            <span class="label label-danger">Critical</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($c->certification_number): ?>
                                            <?php echo htmlspecialchars($c->certification_number); ?>
                                            <?php if ($c->certification_expiry): ?>
                                            <br><small class="text-muted">Exp: <?php echo $c->certification_expiry; ?></small>
                                            <?php endif; ?>
                                            <?php else: ?>
                                            <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($c->is_active): ?>
                                            <span class="label label-success">Active</span>
                                            <?php else: ?>
                                            <span class="label label-danger">Revoked</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($c->is_active): ?>
                                            <form action="<?php echo base_url() ?>app/result_approval/revoke_credential/<?php echo $c->credential_id; ?>" method="post" style="display:inline;">
                                                <button type="submit" class="btn btn-xs btn-danger" onclick="return confirm('Revoke this credential?');">
                                                    <i class="fa fa-ban"></i> Revoke
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </aside>
</div>

<script src="<?php echo base_url() ?>public/plugins/jQuery/jQuery-2.1.4.min.js"></script>
<script src="<?php echo base_url() ?>public/bootstrap/js/bootstrap.min.js"></script>
<script src="<?php echo base_url() ?>public/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="<?php echo base_url() ?>public/plugins/datatables/dataTables.bootstrap.min.js"></script>
<script src="<?php echo base_url() ?>public/plugins/datepicker/bootstrap-datepicker.js"></script>
<script src="<?php echo base_url() ?>public/dist/js/app.min.js"></script>
<script>
$(function() {
    $('#credentialsTable').DataTable({ pageLength: 25 });
    $('.datepicker').datepicker({ format: 'yyyy-mm-dd', autoclose: true });
});
</script>
</body>
</html>
