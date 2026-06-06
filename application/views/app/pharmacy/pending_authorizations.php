<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Pending Authorizations | Hospital Management System</title>
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
        <h1><i class="fa fa-clock-o"></i> Pending Controlled Drug Authorizations</h1>
        <ol class="breadcrumb">
            <li><a href="<?= base_url() ?>app/pharmacy"><i class="fa fa-medkit"></i> Pharmacy</a></li>
            <li><a href="<?= base_url() ?>app/pharmacy/controlled_drugs">Controlled Drugs</a></li>
            <li class="active">Pending Authorizations</li>
        </ol>
    </section>

    <section class="content">
        <?= isset($message) ? $message : '' ?>

        <?php if (empty($pending)): ?>
        <div class="callout callout-success">
            <h4><i class="fa fa-check"></i> No Pending Authorizations</h4>
            <p>There are no controlled drug dispense requests awaiting your authorization.</p>
        </div>
        <?php else: ?>

        <div class="callout callout-warning">
            <h4><i class="fa fa-warning"></i> Double Authentication Required</h4>
            <p>The following controlled drug dispense requests require authorization from a second pharmacist. You cannot authorize requests that you initiated.</p>
        </div>

        <!-- Pending Authorizations -->
        <div class="box box-warning">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-list"></i> Pending Requests (<?= count($pending) ?>)</h3>
            </div>
            <div class="box-body">
                <table class="table table-bordered table-striped" id="pendingTable">
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Drug</th>
                            <th>Schedule</th>
                            <th>Quantity</th>
                            <th>Requested By</th>
                            <th>Requested At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending as $p): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($p->firstname . ' ' . $p->lastname) ?></strong>
                                <br><small class="text-muted"><?= htmlspecialchars($p->patient_no) ?></small>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($p->drug_name) ?></strong>
                                <?php if ($p->batch_no): ?>
                                    <br><small class="text-muted">Batch: <?= htmlspecialchars($p->batch_no) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $scheduleClass = array(
                                    'SCHEDULE_I' => 'danger',
                                    'SCHEDULE_II' => 'danger',
                                    'SCHEDULE_III' => 'warning',
                                    'SCHEDULE_IV' => 'info',
                                    'SCHEDULE_V' => 'default'
                                );
                                $cls = isset($scheduleClass[$p->schedule_code]) ? $scheduleClass[$p->schedule_code] : 'default';
                                ?>
                                <span class="label label-<?= $cls ?>"><?= htmlspecialchars($p->schedule_code) ?></span>
                            </td>
                            <td class="text-center"><strong><?= number_format($p->quantity_dispensed, 2) ?></strong></td>
                            <td><?= htmlspecialchars($p->primary_pharmacist_id) ?></td>
                            <td>
                                <?= date('d M Y H:i', strtotime($p->primary_auth_at)) ?>
                                <br><small class="text-muted"><?= human_time_diff(strtotime($p->primary_auth_at)) ?> ago</small>
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm btn-success btn-authorize"
                                        data-dispense-id="<?= $p->dispense_id ?>"
                                        data-drug-name="<?= htmlspecialchars($p->drug_name) ?>"
                                        data-patient="<?= htmlspecialchars($p->firstname . ' ' . $p->lastname) ?>"
                                        data-quantity="<?= $p->quantity_dispensed ?>"
                                        data-requires-witness="<?= $p->requires_witness ? '1' : '0' ?>">
                                    <i class="fa fa-check"></i> Authorize
                                </button>
                                <button type="button" class="btn btn-sm btn-danger btn-reject"
                                        data-dispense-id="<?= $p->dispense_id ?>"
                                        data-drug-name="<?= htmlspecialchars($p->drug_name) ?>">
                                    <i class="fa fa-times"></i> Reject
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Navigation -->
        <div class="row">
            <div class="col-md-6">
                <a href="<?= base_url() ?>app/pharmacy/controlled_drugs" class="btn btn-block btn-default">
                    <i class="fa fa-shield"></i> Controlled Drugs List
                </a>
            </div>
            <div class="col-md-6">
                <a href="<?= base_url() ?>app/pharmacy" class="btn btn-block btn-default">
                    <i class="fa fa-arrow-left"></i> Back to Worklist
                </a>
            </div>
        </div>
    </section>
</div>

<!-- Authorize Modal -->
<div class="modal fade" id="authorizeModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" id="authorizeForm">
            <div class="modal-content">
                <div class="modal-header bg-green">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title"><i class="fa fa-check"></i> Authorize Controlled Drug Dispense</h4>
                </div>
                <div class="modal-body">
                    <div class="callout callout-info">
                        <p><strong>Drug:</strong> <span id="auth_drug_name"></span></p>
                        <p><strong>Patient:</strong> <span id="auth_patient"></span></p>
                        <p><strong>Quantity:</strong> <span id="auth_quantity"></span></p>
                    </div>

                    <div class="alert alert-warning">
                        <i class="fa fa-warning"></i> By authorizing this dispense, you confirm that:
                        <ul>
                            <li>The prescription is valid and verified</li>
                            <li>Patient identity has been confirmed</li>
                            <li>The quantity is appropriate for the prescription</li>
                        </ul>
                    </div>

                    <div class="form-group" id="witnessGroup" style="display:none;">
                        <label>Witness ID <span class="text-red">*</span></label>
                        <input type="text" name="witness_id" id="witness_id" class="form-control" placeholder="Enter witness staff ID">
                        <small class="text-muted">This schedule requires a witness for dispensing</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="fa fa-check"></i> Authorize Dispense</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" id="rejectForm">
            <input type="hidden" name="reason" id="reject_reason_input">
            <div class="modal-content">
                <div class="modal-header bg-red">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title"><i class="fa fa-times"></i> Reject Controlled Drug Dispense</h4>
                </div>
                <div class="modal-body">
                    <p>You are about to reject the dispense request for: <strong id="reject_drug_name"></strong></p>

                    <div class="form-group">
                        <label>Reason for Rejection <span class="text-red">*</span></label>
                        <textarea id="reject_reason" class="form-control" rows="3" required placeholder="Enter reason for rejection..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger"><i class="fa fa-times"></i> Reject</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
<?php
function human_time_diff($timestamp) {
    $diff = time() - $timestamp;
    if ($diff < 60) return $diff . ' seconds';
    if ($diff < 3600) return floor($diff / 60) . ' minutes';
    if ($diff < 86400) return floor($diff / 3600) . ' hours';
    return floor($diff / 86400) . ' days';
}
?>

$(function() {
    $('#pendingTable').DataTable({
        "order": [[5, "asc"]],
        "pageLength": 25
    });

    $('.btn-authorize').click(function() {
        var dispenseId = $(this).data('dispense-id');
        var drugName = $(this).data('drug-name');
        var patient = $(this).data('patient');
        var quantity = $(this).data('quantity');
        var requiresWitness = $(this).data('requires-witness') === '1';

        $('#authorizeForm').attr('action', '<?= base_url() ?>app/pharmacy/authorize_controlled/' + dispenseId);
        $('#auth_drug_name').text(drugName);
        $('#auth_patient').text(patient);
        $('#auth_quantity').text(quantity);

        if (requiresWitness) {
            $('#witnessGroup').show();
            $('#witness_id').prop('required', true);
        } else {
            $('#witnessGroup').hide();
            $('#witness_id').prop('required', false);
        }

        $('#authorizeModal').modal('show');
    });

    $('.btn-reject').click(function() {
        var dispenseId = $(this).data('dispense-id');
        var drugName = $(this).data('drug-name');

        $('#rejectForm').attr('action', '<?= base_url() ?>app/pharmacy/reject_controlled/' + dispenseId);
        $('#reject_drug_name').text(drugName);
        $('#reject_reason').val('');
        $('#rejectModal').modal('show');
    });

    $('#rejectForm').submit(function() {
        $('#reject_reason_input').val($('#reject_reason').val());
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
