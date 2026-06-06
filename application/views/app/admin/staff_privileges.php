<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title>Hebrew Medical Center — Staff Privileges</title>
        <meta content="width=device-width, initial-scale=1.0" name="viewport">
        <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
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
                <section class="content-header">
                    <h1><i class="fa fa-key"></i> Staff Privileges <small>Dynamic Role & Privilege Assignment</small></h1>
                    <ol class="breadcrumb">
                        <li><a href="<?php echo base_url(); ?>app/dashboard"><i class="fa fa-dashboard"></i> Dashboard</a></li>
                        <li class="active">Staff Privileges</li>
                    </ol>
                </section>

                <section class="content">
        <?php if (isset($message) && $message) { echo $message; } ?>

        <!-- Summary Boxes -->
        <div class="row">
            <div class="col-md-4 col-sm-6 col-xs-12">
                <div class="info-box">
                    <span class="info-box-icon bg-green"><i class="fa fa-key"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Active Privileges</span>
                        <span class="info-box-number"><?php echo isset($summary['active_privileges']) ? $summary['active_privileges'] : 0; ?></span>
                    </div>
                </div>
            </div>
            <div class="col-md-4 col-sm-6 col-xs-12">
                <div class="info-box">
                    <span class="info-box-icon bg-yellow"><i class="fa fa-clock-o"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Pending Stock Requests</span>
                        <span class="info-box-number"><?php echo isset($summary['pending_stock_requests']) ? $summary['pending_stock_requests'] : 0; ?></span>
                    </div>
                </div>
            </div>
            <div class="col-md-4 col-sm-6 col-xs-12">
                <div class="info-box">
                    <span class="info-box-icon bg-aqua"><i class="fa fa-users"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Total Staff</span>
                        <span class="info-box-number"><?php echo is_array($users_list) ? count($users_list) : 0; ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Grant Privilege Form -->
        <div class="row">
            <div class="col-md-12">
                <div class="box box-success">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-plus-circle"></i> Assign Privilege</h3>
                    </div>
                    <form method="POST" action="<?php echo base_url(); ?>app/staff_privileges/grant">
                        <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                        <div class="box-body">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Staff Member <span class="text-red">*</span></label>
                                        <select name="user_id" class="form-control select2" required>
                                            <option value="">-- Select Staff --</option>
                                            <?php if (isset($users_list)) foreach ($users_list as $u) { ?>
                                                <option value="<?php echo $u->user_id; ?>">
                                                    <?php echo htmlspecialchars($u->firstname . ' ' . $u->lastname . ' (' . $u->username . ')'); ?>
                                                </option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Privilege <span class="text-red">*</span></label>
                                        <select name="privilege_name" class="form-control" required>
                                            <option value="">-- Select Privilege --</option>
                                            <?php if (isset($privilege_defs)) foreach ($privilege_defs as $key => $def) { ?>
                                                <option value="<?php echo $key; ?>"><?php echo htmlspecialchars($def['label']); ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label>Expiry Date <small>(optional)</small></label>
                                        <input type="date" name="expiry_date" class="form-control" min="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label>Notes</label>
                                        <input type="text" name="notes" class="form-control" placeholder="Reason...">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label>&nbsp;</label>
                                        <button type="submit" class="btn btn-success btn-block"><i class="fa fa-plus"></i> Grant</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Active Privileges Table -->
        <div class="row">
            <div class="col-md-12">
                <div class="nav-tabs-custom">
                    <ul class="nav nav-tabs">
                        <li class="active"><a href="#tab_active" data-toggle="tab"><i class="fa fa-check-circle"></i> Active Privileges</a></li>
                        <li><a href="#tab_all" data-toggle="tab"><i class="fa fa-list"></i> All History</a></li>
                        <li><a href="#tab_audit" data-toggle="tab"><i class="fa fa-history"></i> Audit Log</a></li>
                    </ul>
                    <div class="tab-content">
                        <!-- Active Tab -->
                        <div class="tab-pane active" id="tab_active">
                            <table class="table table-bordered table-striped table-hover" id="tblActivePriv">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Staff Member</th>
                                        <th>Privilege</th>
                                        <th>Granted By</th>
                                        <th>Granted At</th>
                                        <th>Expiry</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $idx = 0;
                                    if (isset($active_privileges)) foreach ($active_privileges as $p) {
                                        $idx++;
                                        $privLabel = isset($privilege_defs[$p->privilege_name]) ? $privilege_defs[$p->privilege_name]['label'] : $p->privilege_name;
                                        $staffName = trim($p->firstname . ' ' . $p->lastname);
                                        if ($staffName === '') $staffName = $p->username;
                                    ?>
                                    <tr>
                                        <td><?php echo $idx; ?></td>
                                        <td><strong><?php echo htmlspecialchars($staffName); ?></strong><br><small class="text-muted"><?php echo htmlspecialchars($p->username); ?></small></td>
                                        <td><span class="label label-success"><?php echo htmlspecialchars($privLabel); ?></span></td>
                                        <td><?php echo htmlspecialchars($p->granted_by); ?></td>
                                        <td><?php echo date('d M Y H:i', strtotime($p->granted_at)); ?></td>
                                        <td>
                                            <?php if ($p->expiry_date) { ?>
                                                <?php
                                                $exp = strtotime($p->expiry_date);
                                                $daysLeft = (int)ceil(($exp - time()) / 86400);
                                                $cls = $daysLeft <= 3 ? 'label-danger' : ($daysLeft <= 7 ? 'label-warning' : 'label-info');
                                                ?>
                                                <span class="label <?php echo $cls; ?>"><?php echo date('d M Y', $exp); ?> (<?php echo $daysLeft; ?>d left)</span>
                                            <?php } else { ?>
                                                <span class="text-muted">No expiry</span>
                                            <?php } ?>
                                        </td>
                                        <td>
                                            <form method="POST" action="<?php echo base_url(); ?>app/staff_privileges/revoke" style="display:inline" onsubmit="return confirm('Revoke this privilege?')">
                                                <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                                                <input type="hidden" name="privilege_id" value="<?php echo $p->id; ?>">
                                                <button type="submit" class="btn btn-danger btn-xs"><i class="fa fa-times"></i> Revoke</button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php } ?>
                                    <?php if ($idx === 0) { ?>
                                    <tr><td colspan="7" class="text-center text-muted">No active privileges</td></tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- All History Tab -->
                        <div class="tab-pane" id="tab_all">
                            <table class="table table-bordered table-striped table-hover" id="tblAllPriv">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Staff</th>
                                        <th>Privilege</th>
                                        <th>Status</th>
                                        <th>Granted</th>
                                        <th>Expiry</th>
                                        <th>Revoked</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $idx2 = 0;
                                    if (isset($privileges)) foreach ($privileges as $p) {
                                        $idx2++;
                                        $privLabel = isset($privilege_defs[$p->privilege_name]) ? $privilege_defs[$p->privilege_name]['label'] : $p->privilege_name;
                                        $staffName = trim($p->firstname . ' ' . $p->lastname);
                                        if ($staffName === '') $staffName = $p->username;
                                    ?>
                                    <tr>
                                        <td><?php echo $idx2; ?></td>
                                        <td><?php echo htmlspecialchars($staffName); ?></td>
                                        <td><?php echo htmlspecialchars($privLabel); ?></td>
                                        <td>
                                            <?php if ($p->is_active) { ?>
                                                <span class="label label-success">Active</span>
                                            <?php } else { ?>
                                                <span class="label label-default">Revoked</span>
                                            <?php } ?>
                                        </td>
                                        <td><?php echo date('d M Y H:i', strtotime($p->granted_at)); ?><br><small><?php echo htmlspecialchars($p->granted_by); ?></small></td>
                                        <td><?php echo $p->expiry_date ? date('d M Y', strtotime($p->expiry_date)) : '-'; ?></td>
                                        <td><?php echo $p->revoked_at ? date('d M Y H:i', strtotime($p->revoked_at)) . '<br><small>' . htmlspecialchars($p->revoked_by) . '</small>' : '-'; ?></td>
                                    </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Audit Log Tab -->
                        <div class="tab-pane" id="tab_audit">
                            <table class="table table-bordered table-striped table-condensed" id="tblAudit">
                                <thead>
                                    <tr>
                                        <th>Date/Time</th>
                                        <th>User ID</th>
                                        <th>Privilege</th>
                                        <th>Action</th>
                                        <th>Performed By</th>
                                        <th>IP</th>
                                        <th>Details</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (isset($audit_log)) foreach ($audit_log as $a) { ?>
                                    <tr>
                                        <td><?php echo date('d M Y H:i', strtotime($a->performed_at)); ?></td>
                                        <td><?php echo htmlspecialchars($a->user_id); ?></td>
                                        <td><?php echo htmlspecialchars($a->privilege_name); ?></td>
                                        <td>
                                            <?php if ($a->action === 'GRANT') { ?>
                                                <span class="label label-success">GRANT</span>
                                            <?php } else { ?>
                                                <span class="label label-danger">REVOKE</span>
                                            <?php } ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($a->actor_name ? $a->actor_name : $a->performed_by); ?></td>
                                        <td><small><?php echo htmlspecialchars($a->ip_address); ?></small></td>
                                        <td><small><?php echo htmlspecialchars($a->details); ?></small></td>
                                    </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
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
        <script src="<?php echo base_url();?>public/js/plugins/datatables/jquery.dataTables.js" type="text/javascript"></script>
        <script src="<?php echo base_url();?>public/js/plugins/datatables/dataTables.bootstrap.js" type="text/javascript"></script>
        <script>
        $(function(){
            if($.fn.DataTable){
                // Only initialize DataTables if there's actual data (not just "No data" row)
                var activeTable = $('#tblActivePriv');
                var allTable = $('#tblAllPriv');
                var auditTable = $('#tblAudit');
                
                // Check if tables have real data rows (not colspan placeholder)
                if (activeTable.find('tbody tr').length > 0 && !activeTable.find('tbody tr td[colspan]').length) {
                    activeTable.DataTable({"pageLength":25,"order":[]});
                }
                if (allTable.find('tbody tr').length > 0) {
                    allTable.DataTable({"pageLength":25,"order":[]});
                }
                if (auditTable.find('tbody tr').length > 0) {
                    auditTable.DataTable({"pageLength":25,"order":[]});
                }
            }
            if($.fn.select2){
                $('.select2').select2({width:'100%'});
            }
        });
        </script>
    </body>
</html>
