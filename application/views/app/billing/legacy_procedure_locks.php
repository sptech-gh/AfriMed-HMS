<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Legacy Procedure Locks - Reconciliation</title>
    <link rel="stylesheet" href="<?php echo base_url(); ?>public/css/bootstrap.css">
    <link rel="stylesheet" href="<?php echo base_url(); ?>public/css/font-awesome.css">
    <link rel="stylesheet" href="<?php echo base_url(); ?>public/css/AdminLTE.css">
</head>
<body class="skin-blue">
<?php require_once(APPPATH.'views/include/header.php'); ?>
<div class="wrapper row-offcanvas row-offcanvas-left">
    <?php require_once(APPPATH.'views/include/sidebar.php'); ?>
    <aside class="right-side">
        <section class="content-header">
            <h1><i class="fa fa-balance-scale"></i> Legacy Procedure Locks</h1>
            <ol class="breadcrumb">
                <li><a href="<?php echo base_url(); ?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                <li><a href="<?php echo base_url(); ?>app/billing_reconciliation">Reconciliation</a></li>
                <li class="active">Legacy Procedure Locks</li>
            </ol>
        </section>
        <section class="content">

            <?php if (!empty($flash_success)): ?>
                <div class="alert alert-success"><i class="fa fa-check"></i> <?php echo htmlspecialchars($flash_success); ?></div>
            <?php endif; ?>
            <?php if (!empty($flash_warning)): ?>
                <div class="alert alert-warning"><i class="fa fa-warning"></i> <?php echo htmlspecialchars($flash_warning); ?></div>
            <?php endif; ?>

            <div class="box box-info">
                <div class="box-header">
                    <h3 class="box-title">Audit-Preserving Migration</h3>
                </div>
                <div class="box-body">
                    <p>
                        Identifies legacy <code>IPD_BED_SIDE</code> / <code>IPD_OT</code> billable item locks whose
                        <code>source_ref</code> id no longer maps to a real event row (orphaned by the
                        catalog-PK-to-event-PK format correction) and deactivates them
                        (<code>InActive = 1</code>). Existing rows are <strong>not rewritten</strong>; this preserves
                        the audit trail per Ghana private hospital finance practice.
                    </p>
                    <a class="btn btn-primary"
                       href="<?php echo base_url(); ?>app/billing_reconciliation/migrate_legacy_procedure_locks"
                       onclick="return confirm('Run audit-preserving migration of legacy procedure locks now?');">
                        <i class="fa fa-play-circle"></i> Run Migration
                    </a>
                </div>
            </div>

            <div class="box box-warning">
                <div class="box-header">
                    <h3 class="box-title">Deactivated Legacy Locks (latest <?php echo (int)$limit; ?>)</h3>
                </div>
                <div class="box-body table-responsive">
                    <?php if (empty($locks)): ?>
                        <div class="alert alert-info"><i class="fa fa-info-circle"></i> No deactivated legacy procedure locks found.</div>
                    <?php else: ?>
                        <table class="table table-bordered table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Lock ID</th>
                                    <th>Module</th>
                                    <th>Source Ref</th>
                                    <th>Invoice No</th>
                                    <th>IO_ID</th>
                                    <th>Patient No</th>
                                    <th>Status</th>
                                    <th>Locked At</th>
                                    <th>Migrated At</th>
                                    <th>Migrated By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($locks as $row): ?>
                                <tr>
                                    <td><?php echo (int)$row->lock_id; ?></td>
                                    <td><span class="label label-default"><?php echo htmlspecialchars((string)$row->source_module); ?></span></td>
                                    <td><code><?php echo htmlspecialchars((string)$row->source_ref); ?></code></td>
                                    <td><?php echo htmlspecialchars((string)$row->invoice_no); ?></td>
                                    <td><?php echo htmlspecialchars((string)$row->iop_id); ?></td>
                                    <td><?php echo htmlspecialchars((string)$row->patient_no); ?></td>
                                    <td><?php echo htmlspecialchars((string)$row->status); ?></td>
                                    <td><?php echo htmlspecialchars((string)$row->locked_at); ?></td>
                                    <td><?php echo isset($row->legacy_migrated_at) ? htmlspecialchars((string)$row->legacy_migrated_at) : '-'; ?></td>
                                    <td><?php echo isset($row->legacy_migrated_by) ? htmlspecialchars((string)$row->legacy_migrated_by) : '-'; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

        </section>
    </aside>
</div>

<script src="<?php echo base_url(); ?>public/js/jquery.min.js"></script>
<script src="<?php echo base_url(); ?>public/js/bootstrap.min.js"></script>
<script src="<?php echo base_url(); ?>public/js/AdminLTE/app.js"></script>
</body>
</html>
