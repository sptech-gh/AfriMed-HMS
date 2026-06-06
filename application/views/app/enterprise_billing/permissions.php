<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo isset($companyInfo->site_title) ? $companyInfo->site_title : 'HMS'; ?> — Billing Permissions</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/datatables/dataTables.bootstrap.css" rel="stylesheet" type="text/css" />
</head>
<body class="skin-blue">
    <?php require_once(APPPATH.'views/include/header.php');?>
    <div class="wrapper row-offcanvas row-offcanvas-left">
        <?php require_once(APPPATH.'views/include/sidebar.php');?>
        <aside class="right-side">
<div class="content-wrapper">
    <section class="content-header">
        <h1>Billing Permissions <small>Role-Based Access Control</small></h1>
        <ol class="breadcrumb">
            <li><a href="<?= base_url('app/ebilling') ?>"><i class="fa fa-dashboard"></i> Billing & Finance</a></li>
            <li class="active">Permissions</li>
        </ol>
    </section>

    <section class="content">
        <!-- Role Permissions Matrix -->
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-shield"></i> Role Permissions Matrix</h3>
            </div>
            <div class="box-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Permission</th>
                                <?php foreach ($roles as $role): ?>
                                <th class="text-center"><?= htmlspecialchars($role->role_name) ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $permissions = [
                                'can_create_invoice' => 'Create Invoice',
                                'can_edit_invoice' => 'Edit Invoice',
                                'can_delete_invoice' => 'Delete Invoice',
                                'can_collect_payment' => 'Collect Payment',
                                'can_refund' => 'Process Refunds',
                                'can_view_reports' => 'View Reports',
                                'can_reconcile' => 'Reconciliation',
                                'can_approve_discount' => 'Approve Discounts',
                                'can_view_audit' => 'View Audit Logs',
                                'can_manage_settings' => 'Manage Settings'
                            ];
                            foreach ($permissions as $key => $label):
                            ?>
                            <tr>
                                <td><strong><?= $label ?></strong></td>
                                <?php foreach ($roles as $role): ?>
                                <td class="text-center">
                                    <input type="checkbox" class="perm-checkbox" 
                                           data-role="<?= $role->role_key ?>" 
                                           data-perm="<?= $key ?>"
                                           <?= $role->$key ? 'checked' : '' ?>>
                                </td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="text-right">
                    <button class="btn btn-success" id="savePermissions">
                        <i class="fa fa-save"></i> Save All Permissions
                    </button>
                </div>
            </div>
        </div>

        <!-- User Role Assignments -->
        <div class="row">
            <div class="col-md-6">
                <div class="box box-info">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-user-plus"></i> Assign Billing Role</h3>
                    </div>
                    <div class="box-body">
                        <form id="assignRoleForm">
                            <div class="form-group">
                                <label>Select User:</label>
                                <select name="user_id" class="form-control" required>
                                    <option value="">-- Select User --</option>
                                    <?php foreach ($users as $u): ?>
                                    <option value="<?= $u->user_id ?>">
                                        <?= htmlspecialchars($u->firstname . ' ' . $u->lastname) ?> (<?= $u->username ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Billing Role:</label>
                                <select name="role_key" class="form-control" required>
                                    <option value="">-- Select Role --</option>
                                    <?php foreach ($roles as $role): ?>
                                    <option value="<?= $role->role_key ?>"><?= htmlspecialchars($role->role_name) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fa fa-check"></i> Assign Role
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="box box-default">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-users"></i> Current Assignments</h3>
                    </div>
                    <div class="box-body">
                        <?php if (!empty($user_roles)): ?>
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Billing Role</th>
                                    <th>Assigned</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($user_roles as $ur): ?>
                                <tr>
                                    <td><?= htmlspecialchars($ur->firstname . ' ' . $ur->lastname) ?></td>
                                    <td><span class="label label-primary"><?= htmlspecialchars($ur->billing_role_key) ?></span></td>
                                    <td><small><?= date('M d, Y', strtotime($ur->assigned_at)) ?></small></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <p class="text-muted text-center">No billing roles assigned yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Role Descriptions -->
        <div class="box box-default collapsed-box">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-info-circle"></i> Role Descriptions</h3>
                <div class="box-tools pull-right">
                    <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-plus"></i></button>
                </div>
            </div>
            <div class="box-body">
                <dl class="dl-horizontal">
                    <dt>Super Admin</dt>
                    <dd>Full access to all billing functions including settings and permissions management.</dd>
                    
                    <dt>Finance Manager</dt>
                    <dd>Can manage invoices, process payments and refunds, view reports, and approve discounts.</dd>
                    
                    <dt>Cashier</dt>
                    <dd>Can create invoices and collect payments. Cannot edit/delete or process refunds.</dd>
                    
                    <dt>Auditor</dt>
                    <dd>Read-only access to reports, reconciliation, and audit logs. Cannot make changes.</dd>
                    
                    <dt>Department User</dt>
                    <dd>Limited access for department-specific billing views only.</dd>
                </dl>
            </div>
        </div>
    </section>
</div>
        </aside>
    </div>
    <script src="<?php echo base_url();?>public/js/jquery.min.js"></script>
    <script src="<?php echo base_url();?>public/js/bootstrap.min.js" type="text/javascript"></script>
    <script src="<?php echo base_url();?>public/js/AdminLTE/app.js" type="text/javascript"></script>
    <script>
    $(document).ready(function() {
        // Save permissions
        $('#savePermissions').click(function() {
            var btn = $(this);
            btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');
            
            // Collect permissions by role
            var rolePerms = {};
            $('.perm-checkbox').each(function() {
                var role = $(this).data('role');
                var perm = $(this).data('perm');
                if (!rolePerms[role]) rolePerms[role] = {};
                rolePerms[role][perm] = $(this).is(':checked') ? 1 : 0;
            });
            
            // Save each role
            var roles = Object.keys(rolePerms);
            var saved = 0;
            
            roles.forEach(function(role) {
                var data = rolePerms[role];
                data.role_key = role;
                data['<?= $this->security->get_csrf_token_name() ?>'] = '<?= $this->security->get_csrf_hash() ?>';
                
                $.post('<?= base_url('app/ebilling/update_role_permissions') ?>', data, function(resp) {
                    saved++;
                    if (saved === roles.length) {
                        btn.prop('disabled', false).html('<i class="fa fa-save"></i> Save All Permissions');
                        alert('Permissions saved successfully!');
                    }
                }, 'json');
            });
        });
        
        // Assign role
        $('#assignRoleForm').submit(function(e) {
            e.preventDefault();
            
            var data = $(this).serialize();
            data += '&<?= $this->security->get_csrf_token_name() ?>=<?= $this->security->get_csrf_hash() ?>';
            
            $.post('<?= base_url('app/ebilling/assign_billing_role') ?>', data, function(resp) {
                if (resp.success) {
                    alert('Role assigned successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + (resp.error || 'Unknown error'));
                }
            }, 'json');
        });
    });
    </script>
</body>
</html>
