<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Recall Details | Hospital Management System</title>
    <meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>
    <link href="<?php echo base_url(); ?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/css/datatables/dataTables.bootstrap.css" rel="stylesheet" type="text/css" />
</head>
<body class="skin-blue">
<?php require_once(APPPATH.'views/include/header.php');?>

<div class="wrapper row-offcanvas row-offcanvas-left">
    <?php require_once(APPPATH.'views/include/sidebar.php');?>
    
    <aside class="right-side">
    <section class="content-header">
        <h1><i class="fa fa-exclamation-triangle"></i> Recall Details</h1>
        <ol class="breadcrumb">
            <li><a href="<?= base_url() ?>app/pharmacy"><i class="fa fa-medkit"></i> Pharmacy</a></li>
            <li><a href="<?= base_url() ?>app/pharmacy/batch_recalls">Batch Recalls</a></li>
            <li class="active">Details</li>
        </ol>
    </section>

    <section class="content">
        <?= isset($message) ? $message : '' ?>

        <!-- Recall Info -->
        <div class="box box-<?= $recall->status === 'ACTIVE' ? 'danger' : 'success' ?>">
            <div class="box-header with-border">
                <h3 class="box-title">
                    <i class="fa fa-exclamation-triangle"></i> 
                    <?= htmlspecialchars($recall->drug_name) ?> - Batch: <?= htmlspecialchars($recall->batch_number) ?>
                </h3>
                <div class="box-tools pull-right">
                    <?php if ($recall->status === 'ACTIVE'): ?>
                        <span class="label label-danger"><i class="fa fa-exclamation-triangle"></i> ACTIVE RECALL</span>
                    <?php else: ?>
                        <span class="label label-success"><i class="fa fa-check"></i> RESOLVED</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="box-body">
                <div class="row">
                    <div class="col-md-3">
                        <strong>Recall Type:</strong><br>
                        <?= isset($recall_types[$recall->recall_type]) ? $recall_types[$recall->recall_type] : $recall->recall_type ?>
                    </div>
                    <div class="col-md-3">
                        <strong>Recall Class:</strong><br>
                        <?php if ($recall->recall_class): ?>
                            <span class="label label-<?= $recall->recall_class === 'I' ? 'danger' : ($recall->recall_class === 'II' ? 'warning' : 'info') ?>">
                                <?= isset($recall_classes[$recall->recall_class]) ? $recall_classes[$recall->recall_class] : 'Class ' . $recall->recall_class ?>
                            </span>
                        <?php else: ?>
                            N/A
                        <?php endif; ?>
                    </div>
                    <div class="col-md-3">
                        <strong>Recall Date:</strong><br>
                        <?= date('d M Y', strtotime($recall->recall_date)) ?>
                    </div>
                    <div class="col-md-3">
                        <strong>Effective Date:</strong><br>
                        <?= date('d M Y', strtotime($recall->effective_date)) ?>
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-md-6">
                        <strong>Recall Reason:</strong><br>
                        <?= nl2br(htmlspecialchars($recall->recall_reason)) ?>
                    </div>
                    <div class="col-md-6">
                        <?php if ($recall->instructions): ?>
                        <strong>Instructions:</strong><br>
                        <?= nl2br(htmlspecialchars($recall->instructions)) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($recall->regulatory_reference || $recall->manufacturer): ?>
                <hr>
                <div class="row">
                    <div class="col-md-6">
                        <strong>Regulatory Reference:</strong> <?= htmlspecialchars($recall->regulatory_reference ?: 'N/A') ?>
                    </div>
                    <div class="col-md-6">
                        <strong>Manufacturer:</strong> <?= htmlspecialchars($recall->manufacturer ?: 'N/A') ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <div class="box-footer">
                <div class="row">
                    <div class="col-md-3 text-center">
                        <h4 class="text-red"><?= number_format($recall->affected_qty_in_stock) ?></h4>
                        <small>Units in Stock</small>
                    </div>
                    <div class="col-md-3 text-center">
                        <h4 class="text-yellow"><?= number_format($recall->affected_qty_dispensed) ?></h4>
                        <small>Units Dispensed</small>
                    </div>
                    <div class="col-md-3 text-center">
                        <h4 class="text-blue"><?= count($affected_patients) ?></h4>
                        <small>Affected Patients</small>
                    </div>
                    <div class="col-md-3 text-center">
                        <h4 class="text-green"><?= $recall->patients_notified ?></h4>
                        <small>Patients Notified</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Affected Patients -->
        <div class="box box-warning">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-users"></i> Affected Patients (<?= count($affected_patients) ?>)</h3>
            </div>
            <div class="box-body">
                <?php if (!empty($affected_patients)): ?>
                <table class="table table-bordered table-striped" id="patientsTable">
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Contact</th>
                            <th>Dispensed</th>
                            <th>Qty</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($affected_patients as $p): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($p->firstname . ' ' . $p->lastname) ?></strong>
                                <br><small class="text-muted"><?= htmlspecialchars($p->patient_no) ?></small>
                            </td>
                            <td>
                                <?php if ($p->phone): ?>
                                    <i class="fa fa-phone"></i> <?= htmlspecialchars($p->phone) ?>
                                <?php endif; ?>
                                <?php if ($p->email): ?>
                                    <br><i class="fa fa-envelope"></i> <?= htmlspecialchars($p->email) ?>
                                <?php endif; ?>
                            </td>
                            <td><?= $p->dispensed_date ? date('d M Y', strtotime($p->dispensed_date)) : 'N/A' ?></td>
                            <td><?= number_format($p->quantity_dispensed, 2) ?></td>
                            <td>
                                <?php if ($p->notification_status === 'NOTIFIED'): ?>
                                    <span class="label label-success"><i class="fa fa-check"></i> Notified</span>
                                    <?php if ($p->notification_method): ?>
                                        <br><small><?= htmlspecialchars($p->notification_method) ?></small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="label label-warning"><i class="fa fa-clock-o"></i> Pending</span>
                                <?php endif; ?>
                                <?php if ($p->follow_up_required): ?>
                                    <br><span class="label label-info"><i class="fa fa-flag"></i> Follow-up</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($p->notification_status !== 'NOTIFIED' && $recall->status === 'ACTIVE'): ?>
                                <button type="button" class="btn btn-xs btn-success btn-notify"
                                        data-affected-id="<?= $p->affected_id ?>"
                                        data-patient-name="<?= htmlspecialchars($p->firstname . ' ' . $p->lastname) ?>">
                                    <i class="fa fa-bell"></i> Notify
                                </button>
                                <?php endif; ?>
                                <?php if (!$p->follow_up_required && $recall->status === 'ACTIVE'): ?>
                                <button type="button" class="btn btn-xs btn-info btn-followup"
                                        data-affected-id="<?= $p->affected_id ?>">
                                    <i class="fa fa-flag"></i>
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="alert alert-info">
                    <i class="fa fa-info-circle"></i> No patients were affected by this recall (no dispensing records found for this batch).
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Resolve Section (Admin Only) -->
        <?php if ($recall->status === 'ACTIVE' && $this->session->userdata('role') === 'admin'): ?>
        <div class="box box-success">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-check-circle"></i> Resolve Recall</h3>
            </div>
            <div class="box-body">
                <form method="POST" action="<?= base_url() ?>app/pharmacy/resolve_recall/<?= $recall->recall_id ?>">
                    <div class="form-group">
                        <label>Resolution Notes</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Describe how the recall was resolved..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-success" onclick="return confirm('Are you sure you want to resolve this recall?')">
                        <i class="fa fa-check"></i> Mark as Resolved
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($recall->status === 'RESOLVED'): ?>
        <div class="callout callout-success">
            <h4><i class="fa fa-check"></i> Recall Resolved</h4>
            <p>
                <strong>Resolved by:</strong> <?= htmlspecialchars($recall->resolved_by) ?><br>
                <strong>Resolved at:</strong> <?= date('d M Y H:i', strtotime($recall->resolved_at)) ?><br>
                <?php if ($recall->resolution_notes): ?>
                <strong>Notes:</strong> <?= nl2br(htmlspecialchars($recall->resolution_notes)) ?>
                <?php endif; ?>
            </p>
        </div>
        <?php endif; ?>

        <!-- Navigation -->
        <div class="row">
            <div class="col-md-6">
                <a href="<?= base_url() ?>app/pharmacy/batch_recalls" class="btn btn-block btn-default">
                    <i class="fa fa-arrow-left"></i> Back to Recalls
                </a>
            </div>
            <div class="col-md-6">
                <button type="button" class="btn btn-block btn-info" onclick="window.print()">
                    <i class="fa fa-print"></i> Print Report
                </button>
            </div>
        </div>
    </section>
</div>

<!-- Notify Patient Modal -->
<div class="modal fade" id="notifyModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="<?= base_url() ?>app/pharmacy/notify_patient">
            <input type="hidden" name="recall_id" value="<?= $recall->recall_id ?>">
            <input type="hidden" name="affected_id" id="notify_affected_id">
            <div class="modal-content">
                <div class="modal-header bg-green">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title"><i class="fa fa-bell"></i> Notify Patient: <span id="notify_patient_name"></span></h4>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Notification Method</label>
                        <select name="method" class="form-control">
                            <option value="PHONE">Phone Call</option>
                            <option value="SMS">SMS</option>
                            <option value="EMAIL">Email</option>
                            <option value="IN_PERSON">In Person</option>
                            <option value="LETTER">Letter</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Notes / Patient Response</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Record any patient response or notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="fa fa-check"></i> Mark as Notified</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Follow-up Modal -->
<div class="modal fade" id="followupModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="<?= base_url() ?>app/pharmacy/mark_followup">
            <input type="hidden" name="recall_id" value="<?= $recall->recall_id ?>">
            <input type="hidden" name="affected_id" id="followup_affected_id">
            <div class="modal-content">
                <div class="modal-header bg-info">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title"><i class="fa fa-flag"></i> Mark Follow-up Required</h4>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Follow-up Notes</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Why is follow-up required?"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info"><i class="fa fa-flag"></i> Mark Follow-up</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
$(function() {
    $('#patientsTable').DataTable({
        "order": [[4, "asc"]],
        "pageLength": 25
    });

    $('.btn-notify').click(function() {
        var affectedId = $(this).data('affected-id');
        var patientName = $(this).data('patient-name');
        $('#notify_affected_id').val(affectedId);
        $('#notify_patient_name').text(patientName);
        $('#notifyModal').modal('show');
    });

    $('.btn-followup').click(function() {
        var affectedId = $(this).data('affected-id');
        $('#followup_affected_id').val(affectedId);
        $('#followupModal').modal('show');
    });
});
</script>
    </aside>
</div>

<script src="<?php echo base_url(); ?>public/js/jquery.min.js"></script>
<script src="<?php echo base_url(); ?>public/js/bootstrap.min.js"></script>
<script src="<?php echo base_url(); ?>public/js/plugins/datatables/jquery.dataTables.js"></script>
<script src="<?php echo base_url(); ?>public/js/plugins/datatables/dataTables.bootstrap.js"></script>
<script src="<?php echo base_url(); ?>public/js/AdminLTE/app.js"></script>
</body>
</html>
