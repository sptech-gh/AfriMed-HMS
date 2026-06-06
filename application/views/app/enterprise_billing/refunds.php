<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo isset($companyInfo->site_title) ? $companyInfo->site_title : 'HMS'; ?> — Refunds</title>
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
        <h1>Refund Requests</h1>
        <ol class="breadcrumb">
            <li><a href="<?= base_url('app/ebilling') ?>"><i class="fa fa-dashboard"></i> Billing & Finance</a></li>
            <li class="active">Refunds</li>
        </ol>
    </section>

    <section class="content">
        <div class="box box-danger">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-undo"></i> Refund Requests</h3>
            </div>
            <div class="box-body table-responsive">
                <table class="table table-striped" id="refunds_table">
                    <thead>
                        <tr>
                            <th>Refund No</th>
                            <th>Patient</th>
                            <th>Original Receipt</th>
                            <th>Amount</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th>Requested</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($refunds as $r): ?>
                    <tr>
                        <td><?= $r->refund_no ?></td>
                        <td><?= $r->firstname ?> <?= $r->lastname ?><br><small><?= $r->patient_no ?></small></td>
                        <td><?= $r->original_receipt_no ?></td>
                        <td>GHS <?= number_format($r->refund_amount, 2) ?></td>
                        <td><?= $r->refund_reason ?></td>
                        <td>
                            <?php
                            $status_class = ['PENDING' => 'warning', 'APPROVED' => 'info', 'COMPLETED' => 'success', 'REJECTED' => 'danger'];
                            ?>
                            <span class="label label-<?= $status_class[$r->refund_status] ?? 'default' ?>">
                                <?= $r->refund_status ?>
                            </span>
                        </td>
                        <td><?= date('d/m/Y H:i', strtotime($r->requested_at)) ?></td>
                        <td>
                            <?php if ($r->approval_status === 'PENDING'): ?>
                            <button class="btn btn-xs btn-success btn-approve" data-id="<?= $r->refund_id ?>">
                                <i class="fa fa-check"></i> Approve
                            </button>
                            <?php elseif ($r->approval_status === 'APPROVED' && $r->refund_status === 'PENDING'): ?>
                            <button class="btn btn-xs btn-primary btn-process" data-id="<?= $r->refund_id ?>">
                                <i class="fa fa-play"></i> Process
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>
<script>
var csrfName = '<?php echo $this->security->get_csrf_token_name(); ?>';
var csrfHash = '<?php echo $this->security->get_csrf_hash(); ?>';
$(document).ready(function() {
    $('#refunds_table').DataTable({order: [[6, 'desc']]});
    
    $('.btn-approve').click(function() {
        var id = $(this).data('id');
        if (confirm('Approve this refund request?')) {
            var approveData = {refund_id: id};
            approveData[csrfName] = csrfHash;
            $.post('<?= base_url('app/ebilling/approve_refund') ?>', approveData, function(resp) {
                var data = JSON.parse(resp);
                alert(data.success ? 'Refund approved' : 'Error: ' + data.error);
                if (data.success) location.reload();
            });
        }
    });
    
    $('.btn-process').click(function() {
        var id = $(this).data('id');
        if (confirm('Process this refund? This will reverse the payment.')) {
            var processData = {refund_id: id};
            processData[csrfName] = csrfHash;
            $.post('<?= base_url('app/ebilling/process_refund') ?>', processData, function(resp) {
                var data = JSON.parse(resp);
                alert(data.success ? 'Refund processed' : 'Error: ' + data.error);
                if (data.success) location.reload();
            });
        }
    });
});
</script>
        </aside>
    </div>
    <script src="<?php echo base_url();?>public/js/jquery.min.js"></script>
    <script src="<?php echo base_url();?>public/js/bootstrap.min.js" type="text/javascript"></script>
    <script src="<?php echo base_url();?>public/js/plugins/datatables/jquery.dataTables.js" type="text/javascript"></script>
    <script src="<?php echo base_url();?>public/js/plugins/datatables/dataTables.bootstrap.js" type="text/javascript"></script>
    <script src="<?php echo base_url();?>public/js/AdminLTE/app.js" type="text/javascript"></script>
</body>
</html>
