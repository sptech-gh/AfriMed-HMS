<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo isset($companyInfo->site_title) ? $companyInfo->site_title : 'HMS'; ?> — Collect Payment</title>
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
        <h1>Collect Payment</h1>
        <ol class="breadcrumb">
            <li><a href="<?= base_url('app/ebilling') ?>"><i class="fa fa-dashboard"></i> Billing & Finance</a></li>
            <li class="active">Payment</li>
        </ol>
    </section>

    <section class="content">
        <div class="row">
            <div class="col-md-8">
                <div class="box box-success">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-money"></i> Payment Details</h3>
                    </div>
                    <form id="payment_form">
                        <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                        <div class="box-body">
                            <?php if (isset($invoice)): ?>
                            <input type="hidden" name="invoice_id" value="<?= $invoice->invoice_id ?>">
                            
                            <div class="callout callout-info">
                                <h4>Invoice: <?= $invoice->invoice_no ?></h4>
                                <p>
                                    Patient: <strong><?= $patient->firstname ?> <?= $patient->lastname ?></strong><br>
                                    Total: <strong>GHS <?= number_format($invoice->patient_amount, 2) ?></strong><br>
                                    Paid: <strong>GHS <?= number_format($invoice->amount_paid, 2) ?></strong><br>
                                    Balance: <strong class="text-danger">GHS <?= number_format($invoice->amount_outstanding, 2) ?></strong>
                                </p>
                            </div>
                            <?php else: ?>
                            <div class="form-group">
                                <label>Search Invoice</label>
                                <input type="text" class="form-control" id="invoice_search" placeholder="Enter Invoice Number...">
                            </div>
                            <?php endif; ?>

                            <div class="form-group">
                                <label>Payment Amount (GHS) *</label>
                                <input type="number" step="0.01" class="form-control input-lg" name="payment_amount" 
                                       value="<?= isset($invoice) ? $invoice->amount_outstanding : '' ?>" required>
                            </div>

                            <div class="form-group">
                                <label>Payment Method *</label>
                                <div class="btn-group btn-group-justified" data-toggle="buttons">
                                    <?php foreach ($payment_methods as $method): ?>
                                    <label class="btn btn-default <?= $method == 'CASH' ? 'active' : '' ?>">
                                        <input type="radio" name="payment_method" value="<?= $method ?>" 
                                               <?= $method == 'CASH' ? 'checked' : '' ?>> 
                                        <?= $method ?>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="form-group" id="reference_group" style="display:none;">
                                <label>Reference Number</label>
                                <input type="text" class="form-control" name="payment_reference" 
                                       placeholder="Transaction ID / Cheque No">
                            </div>
                        </div>
                        <div class="box-footer">
                            <button type="submit" class="btn btn-success btn-lg btn-block">
                                <i class="fa fa-check"></i> Process Payment
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="col-md-4">
                <div class="box box-info">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-calculator"></i> Quick Calculator</h3>
                    </div>
                    <div class="box-body">
                        <div class="form-group">
                            <label>Amount Tendered</label>
                            <input type="number" step="0.01" class="form-control" id="tendered">
                        </div>
                        <div class="form-group">
                            <label>Change Due</label>
                            <input type="text" class="form-control input-lg text-success" id="change" readonly>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<script>
$(document).ready(function() {
    $('input[name="payment_method"]').change(function() {
        var method = $(this).val();
        if (method !== 'CASH') {
            $('#reference_group').show();
        } else {
            $('#reference_group').hide();
        }
    });

    $('#tendered').on('input', function() {
        var tendered = parseFloat($(this).val()) || 0;
        var amount = parseFloat($('input[name="payment_amount"]').val()) || 0;
        var change = tendered - amount;
        $('#change').val(change >= 0 ? 'GHS ' + change.toFixed(2) : 'Insufficient');
    });

    $('#payment_form').submit(function(e) {
        e.preventDefault();
        var formData = $(this).serialize();
        
        $.post('<?= base_url('app/ebilling/process_payment') ?>', formData, function(resp) {
            var data = JSON.parse(resp);
            if (data.success) {
                if (confirm('Payment successful! Receipt: ' + data.receipt_no + '\n\nPrint receipt?')) {
                    window.open('<?= base_url('app/ebilling/print_receipt/') ?>' + data.payment_id, '_blank');
                }
                window.location.href = '<?= base_url('app/ebilling') ?>';
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
