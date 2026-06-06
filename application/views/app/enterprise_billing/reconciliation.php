<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo isset($companyInfo->site_title) ? $companyInfo->site_title : 'HMS'; ?> — Billing Reconciliation</title>
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
        <h1>Billing Reconciliation <small>Discrepancy Detection</small></h1>
        <ol class="breadcrumb">
            <li><a href="<?= base_url('app/ebilling') ?>"><i class="fa fa-dashboard"></i> Billing & Finance</a></li>
            <li class="active">Reconciliation</li>
        </ol>
    </section>

    <section class="content">
        <!-- Summary Cards -->
        <div class="row">
            <div class="col-md-3">
                <div class="info-box bg-red">
                    <span class="info-box-icon"><i class="fa fa-exclamation-circle"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Critical</span>
                        <span class="info-box-number"><?= $summary['critical'] ?></span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="info-box bg-yellow">
                    <span class="info-box-icon"><i class="fa fa-warning"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">High</span>
                        <span class="info-box-number"><?= $summary['high'] ?></span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="info-box bg-aqua">
                    <span class="info-box-icon"><i class="fa fa-info-circle"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Medium</span>
                        <span class="info-box-number"><?= $summary['medium'] ?></span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="info-box bg-green">
                    <span class="info-box-icon"><i class="fa fa-check"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Low</span>
                        <span class="info-box-number"><?= $summary['low'] ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Discrepancies Table -->
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-list"></i> Detected Discrepancies (<?= $summary['total'] ?>)</h3>
                <?php if (!empty($discrepancies)): ?>
                <div class="box-tools">
                    <button class="btn btn-warning" id="btn_fix_all_legacy">
                        <i class="fa fa-magic"></i> Fix All Legacy Unbilled
                    </button>
                </div>
                <?php endif; ?>
            </div>
            <div class="box-body table-responsive">
                <?php if (empty($discrepancies)): ?>
                <div class="callout callout-success">
                    <h4><i class="fa fa-check"></i> All Clear!</h4>
                    <p>No billing discrepancies detected. Financial records are in order.</p>
                </div>
                <?php else: ?>
                <table class="table table-striped table-hover" id="discrepancies_table">
                    <thead>
                        <tr>
                            <th>Severity</th>
                            <th>Type</th>
                            <th>Module</th>
                            <th>Patient</th>
                            <th>Description</th>
                            <th>Amount</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($discrepancies as $d): ?>
                    <tr>
                        <td>
                            <?php
                            $severity_class = ['CRITICAL' => 'danger', 'HIGH' => 'warning', 'MEDIUM' => 'info', 'LOW' => 'default'];
                            ?>
                            <span class="label label-<?= $severity_class[$d['severity']] ?? 'default' ?>">
                                <?= $d['severity'] ?>
                            </span>
                        </td>
                        <td><?= $d['type'] ?></td>
                        <td><?= $d['module'] ?></td>
                        <td>
                            <?php if (!empty($d['patient_no'])): ?>
                            <a href="<?= base_url('app/enterprise_billing/patient/' . $d['patient_no']) ?>">
                                <?= $d['patient_no'] ?>
                            </a>
                            <?php else: ?>
                            -
                            <?php endif; ?>
                        </td>
                        <td><?= $d['description'] ?></td>
                        <td>
                            <?php if (!empty($d['amount'])): ?>
                            GHS <?= number_format($d['amount'], 2) ?>
                            <?php else: ?>
                            -
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="btn btn-xs btn-primary btn-fix" 
                                    data-type="<?= $d['type'] ?>" 
                                    data-ref="<?= $d['reference_id'] ?? '' ?>">
                                <i class="fa fa-wrench"></i> Fix
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </section>
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
    $('#discrepancies_table').DataTable({
        order: [[0, 'asc']],
        pageLength: 25
    });
    
    $('.btn-fix').click(function() {
        var type = $(this).data('type');
        var ref = $(this).data('ref');
        
        if (confirm('Attempt to auto-fix this discrepancy?')) {
            $.post('<?= base_url('app/ebilling/fix_discrepancy') ?>', 
                {
                    type: type, 
                    reference_id: ref,
                    '<?= $this->security->get_csrf_token_name() ?>': '<?= $this->security->get_csrf_hash() ?>'
                }, 
                function(resp) {
                    var data = JSON.parse(resp);
                    if (data.success) {
                        alert('Fix applied successfully');
                        location.reload();
                    } else {
                        alert('Error: ' + data.error);
                    }
                }
            );
        }
    });
    
    $('#btn_fix_all_legacy').click(function() {
        if (confirm('This will mark all legacy unbilled services as "billed through legacy system".\n\nThis is useful for cleaning up old data before testing the new billing system.\n\nProceed?')) {
            var btn = $(this);
            btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Processing...');
            
            $.ajax({
                url: '<?= base_url('app/ebilling/fix_all_legacy') ?>',
                type: 'POST',
                data: {
                    '<?= $this->security->get_csrf_token_name() ?>': '<?= $this->security->get_csrf_hash() ?>'
                },
                dataType: 'json',
                timeout: 60000,
                success: function(data) {
                    if (data.success) {
                        alert('Fixed ' + data.fixed + ' unbilled services.\n\nThe reconciliation dashboard will now show a clean state.');
                        location.reload();
                    } else {
                        alert('Error: ' + (data.error || 'Unknown error'));
                        btn.prop('disabled', false).html('<i class="fa fa-magic"></i> Fix All Legacy Unbilled');
                    }
                },
                error: function(xhr, status, error) {
                    alert('Request failed. Please try again or contact support.');
                    btn.prop('disabled', false).html('<i class="fa fa-magic"></i> Fix All Legacy Unbilled');
                }
            });
        }
    });
});
</script>
</body>
</html>
