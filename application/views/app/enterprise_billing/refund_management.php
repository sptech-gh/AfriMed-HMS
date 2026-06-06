<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo isset($companyInfo->site_title) ? $companyInfo->site_title : 'HMS'; ?> — Refund Management</title>
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
        <h1>Refund Management <small>Process & Track Refunds</small></h1>
        <ol class="breadcrumb">
            <li><a href="<?= base_url('app/ebilling') ?>"><i class="fa fa-dashboard"></i> Billing & Finance</a></li>
            <li class="active">Refunds</li>
        </ol>
    </section>

    <section class="content">
        <?php if (!empty($pending_refunds)): ?>
        <!-- Pending Refunds -->
        <div class="box box-warning">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-clock-o"></i> Pending Approval (<?= count($pending_refunds) ?>)</h3>
            </div>
            <div class="box-body">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Refund #</th>
                            <th>Patient</th>
                            <th>Invoice</th>
                            <th>Amount</th>
                            <th>Reason</th>
                            <th>Requested By</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_refunds as $r): ?>
                        <tr id="refund_row_<?= $r->id ?>">
                            <td><strong><?= htmlspecialchars($r->refund_no) ?></strong></td>
                            <td>
                                <?= htmlspecialchars($r->patient_no) ?><br>
                                <small><?= htmlspecialchars(($r->firstname ?? '') . ' ' . ($r->lastname ?? '')) ?></small>
                            </td>
                            <td><?= htmlspecialchars($r->invoice_no ?? $r->invoice_id) ?></td>
                            <td class="text-right"><strong><?= number_format($r->amount, 2) ?></strong></td>
                            <td><?= htmlspecialchars($r->reason) ?></td>
                            <td><?= htmlspecialchars($r->requested_by_name ?? 'Unknown') ?></td>
                            <td><?= date('M d, Y H:i', strtotime($r->created_at)) ?></td>
                            <td>
                                <button class="btn btn-success btn-sm btn-approve" data-id="<?= $r->id ?>">
                                    <i class="fa fa-check"></i> Approve
                                </button>
                                <button class="btn btn-danger btn-sm btn-reject" data-id="<?= $r->id ?>">
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

        <!-- All Refunds -->
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-list"></i> All Refunds</h3>
            </div>
            <div class="box-body">
                <table id="refunds_table" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Refund #</th>
                            <th>Patient</th>
                            <th>Invoice</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Status</th>
                            <th>Requested</th>
                            <th>Approved By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($all_refunds)): ?>
                            <?php foreach ($all_refunds as $r): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($r->refund_no ?? '') ?></strong></td>
                                <td>
                                    <?= htmlspecialchars($r->patient_no ?? '') ?><br>
                                    <small><?= htmlspecialchars(($r->firstname ?? '') . ' ' . ($r->lastname ?? '')) ?></small>
                                </td>
                                <td><?= htmlspecialchars($r->invoice_no ?? ($r->invoice_id ?? '')) ?></td>
                                <td class="text-right"><strong><?= number_format($r->amount ?? 0, 2) ?></strong></td>
                                <td><?= htmlspecialchars($r->refund_method ?? '') ?></td>
                                <td>
                                    <?php
                                    $status = $r->status ?? 'PENDING';
                                    $status_class = 'default';
                                    if ($status === 'APPROVED') $status_class = 'success';
                                    elseif ($status === 'REJECTED') $status_class = 'danger';
                                    elseif ($status === 'PENDING') $status_class = 'warning';
                                    elseif ($status === 'COMPLETED') $status_class = 'info';
                                    ?>
                                    <span class="label label-<?= $status_class ?>"><?= $status ?></span>
                                </td>
                                <td>
                                    <?= isset($r->created_at) ? date('M d, Y', strtotime($r->created_at)) : '-' ?><br>
                                    <small class="text-muted"><?= htmlspecialchars($r->requested_by_name ?? '') ?></small>
                                </td>
                                <td>
                                    <?php if (!empty($r->approved_by)): ?>
                                        <?= htmlspecialchars($r->approved_by_name ?? 'Unknown') ?><br>
                                        <small class="text-muted"><?= !empty($r->approved_at) ? date('M d, Y', strtotime($r->approved_at)) : '' ?></small>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Reject Refund</h4>
            </div>
            <div class="modal-body">
                <input type="hidden" id="reject_refund_id">
                <div class="form-group">
                    <label>Rejection Reason:</label>
                    <textarea id="reject_reason" class="form-control" rows="3" required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmReject">Reject Refund</button>
            </div>
        </div>
    </div>
</div>

        </aside>
    </div>
    <script src="<?php echo base_url();?>public/js/jquery.min.js"></script>
    <script src="<?php echo base_url();?>public/js/bootstrap.min.js" type="text/javascript"></script>
    <script src="<?php echo base_url();?>public/js/plugins/datatables/jquery.dataTables.js" type="text/javascript"></script>
    <script src="<?php echo base_url();?>public/js/plugins/datatables/dataTables.bootstrap.js" type="text/javascript"></script>
    <script src="<?php echo base_url();?>public/js/AdminLTE/app.js" type="text/javascript"></script>
    <script>
    $(document).ready(function() {
        $('#refunds_table').DataTable({
            order: [[6, 'desc']],
            pageLength: 25,
            language: {
                emptyTable: "No refunds found"
            }
        });
        
        // Approve refund
        $('.btn-approve').click(function() {
            var id = $(this).data('id');
            if (confirm('Approve this refund?')) {
                $.post('<?= base_url('app/ebilling/approve_refund') ?>', {
                    refund_id: id,
                    '<?= $this->security->get_csrf_token_name() ?>': '<?= $this->security->get_csrf_hash() ?>'
                }, function(resp) {
                    if (resp.success) {
                        alert('Refund approved');
                        location.reload();
                    } else {
                        alert('Error: ' + resp.error);
                    }
                }, 'json');
            }
        });
        
        // Reject refund - show modal
        $('.btn-reject').click(function() {
            $('#reject_refund_id').val($(this).data('id'));
            $('#reject_reason').val('');
            $('#rejectModal').modal('show');
        });
        
        // Confirm reject
        $('#confirmReject').click(function() {
            var id = $('#reject_refund_id').val();
            var reason = $('#reject_reason').val();
            
            if (!reason) {
                alert('Please provide a rejection reason');
                return;
            }
            
            $.post('<?= base_url('app/ebilling/reject_refund') ?>', {
                refund_id: id,
                reason: reason,
                '<?= $this->security->get_csrf_token_name() ?>': '<?= $this->security->get_csrf_hash() ?>'
            }, function(resp) {
                if (resp.success) {
                    alert('Refund rejected');
                    location.reload();
                } else {
                    alert('Error: ' + resp.error);
                }
            }, 'json');
        });
    });
    </script>
</body>
</html>
