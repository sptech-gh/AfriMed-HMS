<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo isset($companyInfo->site_title) ? $companyInfo->site_title : 'HMS'; ?> — Cashier Reconciliation</title>
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
        <h1>Cashier Reconciliation <small>End of Day Settlement</small></h1>
        <ol class="breadcrumb">
            <li><a href="<?= base_url('app/ebilling') ?>"><i class="fa fa-dashboard"></i> Billing & Finance</a></li>
            <li class="active">Cashier Reconciliation</li>
        </ol>
    </section>

    <section class="content">
        <!-- Date Selection -->
        <div class="box box-default">
            <div class="box-body">
                <form method="get" class="form-inline">
                    <div class="form-group">
                        <label>Reconciliation Date:</label>
                        <input type="date" name="date" class="form-control" value="<?= $reconciliation_date ?>">
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> Load</button>
                </form>
            </div>
        </div>

        <!-- Reconciliation Form -->
        <div class="row">
            <div class="col-md-8">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-calculator"></i> Cash Count</h3>
                    </div>
                    <form id="reconciliationForm">
                        <input type="hidden" name="date" value="<?= $reconciliation_date ?>">
                        <input type="hidden" name="type" value="CASHIER">
                        <div class="box-body">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Payment Method</th>
                                        <th class="text-right">Expected (System)</th>
                                        <th class="text-right">Actual (Counted)</th>
                                        <th class="text-right">Difference</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><i class="fa fa-money text-success"></i> Cash</td>
                                        <td class="text-right">
                                            <input type="hidden" name="cash_expected" value="<?= $expected['cash'] ?>">
                                            <strong><?= number_format($expected['cash'], 2) ?></strong>
                                        </td>
                                        <td>
                                            <input type="number" step="0.01" name="cash_actual" class="form-control text-right actual-input" value="0" data-expected="<?= $expected['cash'] ?>">
                                        </td>
                                        <td class="text-right diff-cell" id="cash_diff">0.00</td>
                                    </tr>
                                    <tr>
                                        <td><i class="fa fa-mobile text-warning"></i> Mobile Money</td>
                                        <td class="text-right">
                                            <input type="hidden" name="momo_expected" value="<?= $expected['momo'] ?>">
                                            <strong><?= number_format($expected['momo'], 2) ?></strong>
                                        </td>
                                        <td>
                                            <input type="number" step="0.01" name="momo_actual" class="form-control text-right actual-input" value="0" data-expected="<?= $expected['momo'] ?>">
                                        </td>
                                        <td class="text-right diff-cell" id="momo_diff">0.00</td>
                                    </tr>
                                    <tr>
                                        <td><i class="fa fa-credit-card text-primary"></i> Card</td>
                                        <td class="text-right">
                                            <input type="hidden" name="card_expected" value="<?= $expected['card'] ?>">
                                            <strong><?= number_format($expected['card'], 2) ?></strong>
                                        </td>
                                        <td>
                                            <input type="number" step="0.01" name="card_actual" class="form-control text-right actual-input" value="0" data-expected="<?= $expected['card'] ?>">
                                        </td>
                                        <td class="text-right diff-cell" id="card_diff">0.00</td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr class="info">
                                        <th>TOTAL</th>
                                        <th class="text-right">
                                            <input type="hidden" name="expected_amount" value="<?= $expected['total'] ?>">
                                            <?= number_format($expected['total'], 2) ?>
                                        </th>
                                        <th class="text-right" id="total_actual">0.00</th>
                                        <th class="text-right" id="total_diff">0.00</th>
                                    </tr>
                                </tfoot>
                            </table>

                            <div class="form-group">
                                <label>Notes / Explanation:</label>
                                <textarea name="notes" class="form-control" rows="3" placeholder="Explain any discrepancies..."></textarea>
                            </div>
                        </div>
                        <div class="box-footer">
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="fa fa-check"></i> Submit Reconciliation
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="col-md-4">
                <!-- Summary -->
                <div class="box box-info">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-info-circle"></i> Summary</h3>
                    </div>
                    <div class="box-body">
                        <div class="info-box bg-aqua">
                            <span class="info-box-icon"><i class="fa fa-money"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Expected Total</span>
                                <span class="info-box-number"><?= number_format($expected['total'], 2) ?></span>
                            </div>
                        </div>
                        
                        <div id="status_box" class="callout callout-info">
                            <h4><i class="fa fa-info"></i> Status</h4>
                            <p>Enter your actual cash counts to see the reconciliation status.</p>
                        </div>
                    </div>
                </div>

                <!-- History -->
                <?php if (!empty($history)): ?>
                <div class="box box-default">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-history"></i> Recent History</h3>
                    </div>
                    <div class="box-body no-padding">
                        <ul class="list-group">
                            <?php foreach ($history as $h): ?>
                            <li class="list-group-item">
                                <span class="badge bg-<?= $h->status === 'BALANCED' ? 'green' : ($h->status === 'SHORTAGE' ? 'red' : 'yellow') ?>">
                                    <?= $h->status ?>
                                </span>
                                <?= date('M d, Y', strtotime($h->reconciliation_date)) ?>
                                <br><small class="text-muted">Diff: <?= number_format($h->difference, 2) ?></small>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <?php endif; ?>
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
        function updateCalculations() {
            var totalActual = 0;
            var totalExpected = <?= $expected['total'] ?>;
            
            $('.actual-input').each(function() {
                var actual = parseFloat($(this).val()) || 0;
                var expected = parseFloat($(this).data('expected')) || 0;
                var diff = actual - expected;
                var name = $(this).attr('name').replace('_actual', '');
                
                totalActual += actual;
                
                $('#' + name + '_diff').text(diff.toFixed(2));
                if (diff < 0) {
                    $('#' + name + '_diff').removeClass('text-success text-muted').addClass('text-danger');
                } else if (diff > 0) {
                    $('#' + name + '_diff').removeClass('text-danger text-muted').addClass('text-success');
                } else {
                    $('#' + name + '_diff').removeClass('text-danger text-success').addClass('text-muted');
                }
            });
            
            var totalDiff = totalActual - totalExpected;
            $('#total_actual').text(totalActual.toFixed(2));
            $('#total_diff').text(totalDiff.toFixed(2));
            
            // Update hidden field
            $('input[name="actual_amount"]').remove();
            $('#reconciliationForm').append('<input type="hidden" name="actual_amount" value="' + totalActual + '">');
            
            // Update status box
            var statusBox = $('#status_box');
            statusBox.removeClass('callout-info callout-success callout-danger callout-warning');
            
            if (totalDiff === 0) {
                statusBox.addClass('callout-success');
                statusBox.html('<h4><i class="fa fa-check"></i> Balanced</h4><p>Your cash count matches the system records.</p>');
            } else if (totalDiff < 0) {
                statusBox.addClass('callout-danger');
                statusBox.html('<h4><i class="fa fa-exclamation-triangle"></i> Shortage</h4><p>You are short by <strong>' + Math.abs(totalDiff).toFixed(2) + '</strong>. Please explain in notes.</p>');
            } else {
                statusBox.addClass('callout-warning');
                statusBox.html('<h4><i class="fa fa-plus"></i> Overage</h4><p>You have an overage of <strong>' + totalDiff.toFixed(2) + '</strong>. Please explain in notes.</p>');
            }
        }
        
        $('.actual-input').on('input', updateCalculations);
        
        $('#reconciliationForm').submit(function(e) {
            e.preventDefault();
            
            var formData = $(this).serialize();
            formData += '&<?= $this->security->get_csrf_token_name() ?>=<?= $this->security->get_csrf_hash() ?>';
            
            $.post('<?= base_url('app/ebilling/submit_reconciliation') ?>', formData, function(resp) {
                if (resp.success) {
                    alert('Reconciliation submitted successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + (resp.error || 'Unknown error'));
                }
            }, 'json').fail(function() {
                alert('Request failed. Please try again.');
            });
        });
    });
    </script>
</body>
</html>
