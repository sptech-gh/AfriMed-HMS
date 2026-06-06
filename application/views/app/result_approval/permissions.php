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
</head>
<body class="skin-blue sidebar-mini">
<?php require_once(APPPATH . 'views/include/header.php'); ?>

<div class="wrapper row-offcanvas row-offcanvas-left">
    <?php require_once(APPPATH . 'views/include/sidebar.php'); ?>
    
    <aside class="right-side">
        <section class="content-header">
            <h1><i class="fa fa-cog"></i> <?php echo $title; ?></h1>
            <ol class="breadcrumb">
                <li><a href="<?php echo base_url() ?>app/dashboard"><i class="fa fa-dashboard"></i> Dashboard</a></li>
                <li><a href="<?php echo base_url() ?>app/result_approval">Result Approval</a></li>
                <li class="active">Permissions</li>
            </ol>
        </section>

        <section class="content">
            <?php if ($this->session->flashdata('success')): ?>
            <div class="alert alert-success alert-dismissible">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                <?php echo $this->session->flashdata('success'); ?>
            </div>
            <?php endif; ?>

            <!-- Edit Permissions -->
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-edit"></i> Result Edit Permissions</h3>
                    <div class="box-tools">
                        <button type="button" class="btn btn-sm btn-success" data-toggle="modal" data-target="#addPermissionModal">
                            <i class="fa fa-plus"></i> Add Permission
                        </button>
                    </div>
                </div>
                <div class="box-body">
                    <table id="permissionsTable" class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Diagnostic Type</th>
                                <th>Category</th>
                                <th>Critical</th>
                                <th>Allowed Roles</th>
                                <th>Requires Approval</th>
                                <th>Edit Window</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($permissions as $p): ?>
                            <tr>
                                <td>
                                    <span class="label label-<?php echo $p->diagnostic_type == 'LAB' ? 'primary' : ($p->diagnostic_type == 'RADIOLOGY' ? 'info' : 'success'); ?>">
                                        <?php echo $p->diagnostic_type; ?>
                                    </span>
                                </td>
                                <td><?php echo $p->result_category; ?></td>
                                <td>
                                    <?php if ($p->is_critical): ?>
                                    <span class="label label-danger">Yes</span>
                                    <?php else: ?>
                                    <span class="label label-default">No</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    $roles = json_decode($p->allowed_roles, true) ?: [];
                                    foreach ($roles as $role): ?>
                                    <span class="label label-info"><?php echo $role; ?></span>
                                    <?php endforeach; ?>
                                </td>
                                <td>
                                    <?php if ($p->requires_supervisor_approval): ?>
                                    <span class="label label-warning"><i class="fa fa-check"></i> Yes</span>
                                    <?php else: ?>
                                    <span class="label label-default">No</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $p->edit_window_hours; ?> hours</td>
                                <td>
                                    <?php if ($p->is_active): ?>
                                    <span class="label label-success">Active</span>
                                    <?php else: ?>
                                    <span class="label label-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Verification Role Config -->
            <div class="box box-info">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-shield"></i> Verification Role Requirements</h3>
                </div>
                <div class="box-body">
                    <table id="verificationTable" class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Diagnostic Type</th>
                                <th>Test Category</th>
                                <th>Level</th>
                                <th>Required Roles</th>
                                <th>Min Experience</th>
                                <th>Certification</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($verification_config as $v): ?>
                            <tr>
                                <td>
                                    <span class="label label-<?php echo $v->diagnostic_type == 'LAB' ? 'primary' : ($v->diagnostic_type == 'RADIOLOGY' ? 'info' : 'success'); ?>">
                                        <?php echo $v->diagnostic_type; ?>
                                    </span>
                                </td>
                                <td><?php echo $v->test_category; ?></td>
                                <td>
                                    <span class="label label-<?php echo $v->verification_level == 1 ? 'success' : 'warning'; ?>">
                                        Level <?php echo $v->verification_level; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    $roles = json_decode($v->required_roles, true) ?: [];
                                    foreach ($roles as $role): ?>
                                    <span class="label label-info"><?php echo $role; ?></span>
                                    <?php endforeach; ?>
                                </td>
                                <td>
                                    <?php echo $v->min_experience_months > 0 ? $v->min_experience_months . ' months' : '-'; ?>
                                </td>
                                <td>
                                    <?php if ($v->requires_certification): ?>
                                    <span class="label label-warning">Required</span>
                                    <?php else: ?>
                                    <span class="label label-default">Not Required</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($v->is_active): ?>
                                    <span class="label label-success">Active</span>
                                    <?php else: ?>
                                    <span class="label label-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Info Box -->
            <div class="callout callout-info">
                <h4><i class="fa fa-info-circle"></i> Configuration Notes</h4>
                <ul>
                    <li><strong>Edit Permissions:</strong> Define which roles can edit results and under what conditions.</li>
                    <li><strong>Critical Results:</strong> Results flagged as critical require supervisor approval before editing.</li>
                    <li><strong>Edit Window:</strong> Time limit after result entry during which edits are allowed without approval.</li>
                    <li><strong>Verification Levels:</strong> Level 1 is initial verification, Level 2 is supervisory verification.</li>
                    <li><strong>Same User Rule:</strong> The same user cannot perform both Level 1 and Level 2 verification.</li>
                </ul>
            </div>
        </section>
    </aside>
</div>

<!-- Add Permission Modal -->
<div class="modal fade" id="addPermissionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="<?php echo base_url() ?>app/result_approval/save_permission" method="post">
                <div class="modal-header bg-primary">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title"><i class="fa fa-plus"></i> Add Edit Permission</h4>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Diagnostic Type <span class="text-red">*</span></label>
                        <select name="diagnostic_type" class="form-control" required>
                            <option value="LAB">Laboratory</option>
                            <option value="RADIOLOGY">Radiology</option>
                            <option value="SONOGRAPHY">Sonography</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Result Category <span class="text-red">*</span></label>
                        <input type="text" name="result_category" class="form-control" required placeholder="e.g., GENERAL, CRITICAL, PANIC">
                    </div>
                    <div class="form-group">
                        <div class="checkbox">
                            <label><input type="checkbox" name="is_critical" value="1"> Is Critical Result Category</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Allowed Roles</label>
                        <div class="checkbox"><label><input type="checkbox" name="allowed_roles[]" value="admin"> Admin</label></div>
                        <div class="checkbox"><label><input type="checkbox" name="allowed_roles[]" value="lab_tech"> Lab Tech</label></div>
                        <div class="checkbox"><label><input type="checkbox" name="allowed_roles[]" value="senior_lab_tech"> Senior Lab Tech</label></div>
                        <div class="checkbox"><label><input type="checkbox" name="allowed_roles[]" value="lab_supervisor"> Lab Supervisor</label></div>
                        <div class="checkbox"><label><input type="checkbox" name="allowed_roles[]" value="pathologist"> Pathologist</label></div>
                        <div class="checkbox"><label><input type="checkbox" name="allowed_roles[]" value="radiologist"> Radiologist</label></div>
                        <div class="checkbox"><label><input type="checkbox" name="allowed_roles[]" value="sonographer"> Sonographer</label></div>
                    </div>
                    <div class="form-group">
                        <div class="checkbox">
                            <label><input type="checkbox" name="requires_supervisor_approval" value="1"> Requires Supervisor Approval</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Edit Window (hours)</label>
                        <input type="number" name="edit_window_hours" class="form-control" value="24" min="0">
                        <small class="text-muted">0 = no time limit</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="<?php echo base_url() ?>public/plugins/jQuery/jQuery-2.1.4.min.js"></script>
<script src="<?php echo base_url() ?>public/bootstrap/js/bootstrap.min.js"></script>
<script src="<?php echo base_url() ?>public/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="<?php echo base_url() ?>public/plugins/datatables/dataTables.bootstrap.min.js"></script>
<script src="<?php echo base_url() ?>public/dist/js/app.min.js"></script>
<script>
$(function() {
    $('#permissionsTable, #verificationTable').DataTable({ pageLength: 25 });
});
</script>
</body>
</html>
