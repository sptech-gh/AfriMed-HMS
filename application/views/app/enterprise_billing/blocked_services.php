<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo isset($companyInfo->site_title) ? $companyInfo->site_title : 'HMS'; ?> — Blocked Services</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
</head>
<body class="skin-blue">
    <?php require_once(APPPATH.'views/include/header.php');?>
    <div class="wrapper row-offcanvas row-offcanvas-left">
        <?php require_once(APPPATH.'views/include/sidebar.php');?>
        <aside class="right-side">
<div class="content-wrapper">
    <section class="content-header">
        <h1>Blocked Services <small>Awaiting Payment</small></h1>
        <ol class="breadcrumb">
            <li><a href="<?= base_url('app/ebilling') ?>"><i class="fa fa-dashboard"></i> Billing & Finance</a></li>
            <li class="active">Blocked Services</li>
        </ol>
    </section>

    <section class="content">
        <div class="box box-danger">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-lock"></i> Services Blocked Until Payment</h3>
            </div>
            <div class="box-body table-responsive">
                <?php if (empty($blocked)): ?>
                <div class="callout callout-success">
                    <p><i class="fa fa-check"></i> No blocked services. All payments are up to date.</p>
                </div>
                <?php else: ?>
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Service</th>
                            <th>Department</th>
                            <th>Amount Required</th>
                            <th>Blocked Since</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($blocked as $svc): ?>
                    <tr>
                        <td>
                            <a href="<?= base_url('app/enterprise_billing/patient/' . $svc->patient_no) ?>">
                                <?= $svc->firstname ?> <?= $svc->lastname ?>
                            </a>
                            <br><small class="text-muted"><?= $svc->patient_no ?></small>
                        </td>
                        <td><?= $svc->charge_name ?></td>
                        <td><span class="label label-default"><?= $svc->service_type ?></span></td>
                        <td><strong>GHS <?= number_format($svc->patient_amount, 2) ?></strong></td>
                        <td><?= date('d/m/Y H:i', strtotime($svc->created_at)) ?></td>
                        <td>
                            <button class="btn btn-xs btn-success btn-collect" data-gate="<?= $svc->gate_id ?>" 
                                    data-patient="<?= $svc->patient_no ?>" data-amount="<?= $svc->patient_amount ?>">
                                <i class="fa fa-money"></i> Collect
                            </button>
                            <button class="btn btn-xs btn-warning btn-bypass" data-gate="<?= $svc->gate_id ?>">
                                <i class="fa fa-unlock"></i> Bypass
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

<!-- Bypass Modal -->
<div class="modal fade" id="bypassModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="fa fa-unlock"></i> Bypass Service Gate</h4>
            </div>
            <form id="bypass_form">
                <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                <div class="modal-body">
                    <input type="hidden" name="gate_id" id="bypass_gate_id">
                    <div class="alert alert-warning">
                        <i class="fa fa-warning"></i> This will allow the service to proceed without payment. 
                        Admin approval required.
                    </div>
                    <div class="form-group">
                        <label>Reason for Bypass *</label>
                        <textarea name="reason" class="form-control" rows="3" required 
                                  placeholder="Enter reason for bypassing payment requirement..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Bypass Gate</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('.btn-collect').click(function() {
        var patient = $(this).data('patient');
        window.location.href = '<?= base_url('app/ebilling/patient/') ?>' + patient;
    });
    
    $('.btn-bypass').click(function() {
        var gateId = $(this).data('gate');
        $('#bypass_gate_id').val(gateId);
        $('#bypassModal').modal('show');
    });
    
    $('#bypass_form').submit(function(e) {
        e.preventDefault();
        $.post('<?= base_url('app/ebilling/bypass_gate') ?>', $(this).serialize(), function(resp) {
            var data = JSON.parse(resp);
            if (data.success) {
                alert('Service gate bypassed');
                location.reload();
            } else {
                alert('Error: ' + data.error);
            }
        });
    });
});
</script>
        </aside>
    </div>
    <script src="<?php echo base_url();?>public/js/jquery.min.js"></script>
    <script src="<?php echo base_url();?>public/js/bootstrap.min.js" type="text/javascript"></script>
    <script src="<?php echo base_url();?>public/js/AdminLTE/app.js" type="text/javascript"></script>
</body>
</html>
