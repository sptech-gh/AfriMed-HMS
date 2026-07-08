<?php if (!defined('BASEPATH')) exit('No direct script access allowed');
/*
 * grouped_receipt.php
 * Walk-In grouped receipt — shows all services from a single session.
 * Variables available: $client, $transactions (array), $companyInfo, $txn_ids_str
 */

$hospitalName    = (isset($companyInfo) && isset($companyInfo->company_name) && trim((string)$companyInfo->company_name) !== '') ? trim((string)$companyInfo->company_name) : 'SBMC Hospital';
$hospitalAddress = (isset($companyInfo) && isset($companyInfo->address)) ? $companyInfo->address : '';
$hospitalPhone   = (isset($companyInfo) && isset($companyInfo->phone))   ? $companyInfo->phone   : '';

// Grand total + shared payment info from first txn
$grand_total    = 0.0;
$payment_method = '';
$payment_status = '';
$cashier_name   = '';
$transaction_date = '';
$notes          = '';
foreach ($transactions as $txn) {
    $grand_total += (float)$txn->amount;
    if ($payment_method === '') $payment_method = $txn->payment_method;
    if ($payment_status === '') $payment_status = $txn->payment_status;
    if ($cashier_name   === '') $cashier_name   = $txn->cashier_name ?: $txn->cashier_id;
    if ($transaction_date === '') $transaction_date = $txn->transaction_date;
    if ($notes === '' && !empty($txn->notes)) $notes = $txn->notes;
}
$receipt_numbers = array_map(function($t){ return $t->receipt_number; }, $transactions);
$primary_receipt = $receipt_numbers[0];
$client_id = $client->id;
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Grouped Receipt — <?php echo htmlspecialchars($primary_receipt); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo base_url()?>public/css/font-awesome.min.css" rel="stylesheet">
    <link href="<?php echo base_url()?>public/css/AdminLTE.css" rel="stylesheet">
    <style>
        .receipt-wrapper{max-width:640px;margin:0 auto;}
        .receipt-card{background:#fff;border-radius:10px;box-shadow:0 2px 20px rgba(0,0,0,.10);overflow:hidden;}
        .receipt-header{background:linear-gradient(135deg,#1a6fa5,#1e90cc);color:#fff;padding:28px 32px;text-align:center;}
        .receipt-header .hospital-name{font-size:20px;font-weight:700;letter-spacing:-.3px;margin-bottom:4px;}
        .receipt-header .receipt-title{font-size:13px;opacity:.85;text-transform:uppercase;letter-spacing:1px;}
        .receipt-header .receipt-number{font-size:22px;font-weight:700;margin-top:10px;background:rgba(255,255,255,.15);border-radius:6px;padding:6px 16px;display:inline-block;}
        .receipt-body{padding:28px 32px;}
        .receipt-section{margin-bottom:22px;}
        .receipt-section-title{font-size:11px;font-weight:700;text-transform:uppercase;color:#6c757d;letter-spacing:.8px;margin-bottom:10px;padding-bottom:6px;border-bottom:1px solid #f0f0f0;}
        .receipt-row{display:flex;justify-content:space-between;align-items:flex-start;padding:4px 0;font-size:14px;}
        .receipt-row .label{color:#6c757d;flex-shrink:0;width:38%;}
        .receipt-row .value{font-weight:600;text-align:right;word-break:break-word;}
        /* grand total box */
        .grand-total-box{background:#1a3a5c;color:#fff;border-radius:8px;padding:18px 24px;display:flex;justify-content:space-between;align-items:center;margin:20px 0;}
        .grand-total-box .gt-label{font-size:14px;font-weight:600;opacity:.85;}
        .grand-total-box .gt-val{font-size:30px;font-weight:900;}
        /* service block */
        .svc-block{background:#f8fbff;border:1px solid #dbe7f4;border-radius:8px;padding:14px 16px;margin-bottom:14px;}
        .svc-block-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;}
        .svc-block-title{font-size:13px;font-weight:700;color:#1a6fa5;}
        .svc-block-subtotal{font-size:14px;font-weight:800;color:#1a3a5c;}
        .badge-service{display:inline-block;padding:3px 10px;border-radius:10px;font-size:12px;font-weight:600;}
        .svc-Laboratory{background:#dbeafe;color:#1e40af;}
        .svc-Sonography{background:#ede9fe;color:#6d28d9;}
        .svc-Pharmacy{background:#dcfce7;color:#166534;}
        .svc-Procedure{background:#fef3c7;color:#92400e;}
        .svc-Consultation{background:#e0f2fe;color:#0369a1;}
        .svc-Radiology{background:#fce7f3;color:#9d174d;}
        .svc-Other{background:#f3f4f6;color:#374151;}
        .status-badge-Paid{display:inline-block;background:#d1fae5;color:#065f46;padding:4px 12px;border-radius:12px;font-weight:700;font-size:13px;}
        .status-badge-Pending{display:inline-block;background:#fef3c7;color:#92400e;padding:4px 12px;border-radius:12px;font-weight:700;font-size:13px;}
        .status-badge-Cancelled{display:inline-block;background:#fee2e2;color:#991b1b;padding:4px 12px;border-radius:12px;font-weight:700;font-size:13px;}
        .receipt-footer{background:#f8f9fa;border-top:1px solid #e9ecef;padding:16px 32px;text-align:center;font-size:12px;color:#6c757d;}
        .action-bar{display:flex;gap:10px;margin-top:20px;flex-wrap:wrap;}
        .action-bar .btn{flex:1;height:44px;font-weight:600;border-radius:6px;}
        table.items-tbl{width:100%;border-collapse:collapse;font-size:13px;}
        table.items-tbl th{background:#f0f4fa;color:#374151;font-weight:700;padding:5px 8px;text-align:left;}
        table.items-tbl th.r, table.items-tbl td.r{text-align:right;}
        table.items-tbl td{padding:5px 8px;border-bottom:1px solid #f0f0f0;}
    </style>
</head>
<body class="skin-blue">
<?php require_once(APPPATH.'views/include/header.php'); ?>

<div class="wrapper row-offcanvas row-offcanvas-left">
    <?php require_once(APPPATH.'views/include/sidebar.php'); ?>

    <aside class="right-side">
        <section class="content-header">
            <h1><i class="fa fa-file-text-o"></i> Payment Receipt
                <small>Grouped Session Receipt</small>
            </h1>
            <ol class="breadcrumb">
                <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                <li><a href="<?php echo base_url()?>app/walkin">Walk-In</a></li>
                <li class="active">Grouped Receipt</li>
            </ol>
        </section>

        <section class="content">
            <?php if(isset($message) && $message) echo $message; ?>

            <div class="receipt-wrapper">
                <!-- Success alert -->
                <div class="alert alert-success" style="border-radius:8px;margin-bottom:16px;">
                    <i class="fa fa-check-circle"></i> <strong>Transaction Complete!</strong>
                    <?php echo count($transactions); ?> service(s) recorded for <strong><?php echo htmlspecialchars($client->client_name); ?></strong>.
                </div>

                <div class="receipt-card">
                    <!-- Header -->
                    <div class="receipt-header">
                        <div class="hospital-name"><?php echo htmlspecialchars($hospitalName); ?></div>
                        <?php if($hospitalAddress): ?>
                        <div style="font-size:12px;opacity:.8;margin-top:2px;"><?php echo htmlspecialchars($hospitalAddress); ?></div>
                        <?php endif; ?>
                        <div class="receipt-title">Walk-In Service Receipt</div>
                        <div class="receipt-number"><?php echo htmlspecialchars($primary_receipt); ?></div>
                        <?php if(count($receipt_numbers) > 1): ?>
                        <div style="font-size:11px;opacity:.75;margin-top:4px;">
                            + <?php echo implode(', ', array_slice($receipt_numbers, 1)); ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Body -->
                    <div class="receipt-body">

                        <!-- Grand Total -->
                        <div class="grand-total-box">
                            <span class="gt-label"><i class="fa fa-calculator"></i> Grand Total</span>
                            <span class="gt-val">GHS <?php echo number_format($grand_total, 2); ?></span>
                        </div>

                        <!-- Status -->
                        <div style="text-align:center;margin-bottom:20px;">
                            <span class="status-badge-<?php echo htmlspecialchars($payment_status); ?>"><?php echo htmlspecialchars($payment_status); ?></span>
                        </div>

                        <!-- Client -->
                        <div class="receipt-section">
                            <div class="receipt-section-title"><i class="fa fa-user"></i> Client Information</div>
                            <div class="receipt-row">
                                <span class="label">Name</span>
                                <span class="value"><?php echo htmlspecialchars($client->client_name); ?></span>
                            </div>
                            <?php if($client->phone): ?>
                            <div class="receipt-row">
                                <span class="label">Phone</span>
                                <span class="value"><?php echo htmlspecialchars($client->phone); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if($client->gender): ?>
                            <div class="receipt-row">
                                <span class="label">Gender</span>
                                <span class="value"><?php echo htmlspecialchars($client->gender); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if(!empty($client->referral)): ?>
                            <div class="receipt-row">
                                <span class="label">Referred By</span>
                                <span class="value"><?php echo htmlspecialchars($client->referral); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Services (grouped) -->
                        <div class="receipt-section">
                            <div class="receipt-section-title"><i class="fa fa-stethoscope"></i> Services Rendered</div>

                            <?php foreach ($transactions as $txn): ?>
                            <div class="svc-block">
                                <div class="svc-block-header">
                                    <span class="svc-block-title">
                                        <span class="badge-service svc-<?php echo htmlspecialchars($txn->service_type); ?>"><?php echo htmlspecialchars($txn->service_type); ?></span>
                                    </span>
                                    <span class="svc-block-subtotal">GHS <?php echo number_format((float)$txn->amount, 2); ?></span>
                                </div>

                                <?php if ($txn->service_type === 'Pharmacy' && isset($txn->items) && is_array($txn->items) && count($txn->items) > 0): ?>
                                    <table class="items-tbl">
                                        <thead><tr><th>Drug</th><th class="r">Qty</th><th class="r">Unit</th><th class="r">Line</th></tr></thead>
                                        <tbody>
                                        <?php foreach ($txn->items as $it): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($it->drug_name ?: ('Drug #' . (int)$it->drug_id)); ?></td>
                                                <td class="r"><?php echo number_format((float)$it->qty, 2); ?></td>
                                                <td class="r">GHS <?php echo number_format((float)$it->unit_price, 2); ?></td>
                                                <td class="r">GHS <?php echo number_format((float)$it->line_total, 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php elseif ($txn->service_type !== 'Pharmacy' && isset($txn->service_items) && is_array($txn->service_items) && count($txn->service_items) > 0): ?>
                                    <table class="items-tbl">
                                        <thead><tr><th>Item</th><th class="r">Qty</th><th class="r">Unit</th><th class="r">Line</th></tr></thead>
                                        <tbody>
                                        <?php foreach ($txn->service_items as $it): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($it->item_name); ?></td>
                                                <td class="r"><?php echo number_format((float)$it->qty, 2); ?></td>
                                                <td class="r">GHS <?php echo number_format((float)$it->unit_price, 2); ?></td>
                                                <td class="r">GHS <?php echo number_format((float)$it->line_total, 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php else: ?>
                                    <div style="font-size:13px;color:#4a6278;"><?php echo htmlspecialchars($txn->description); ?></div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>

                            <!-- Grand total line -->
                            <div style="text-align:right;font-size:16px;font-weight:800;color:#1a3a5c;padding-top:6px;border-top:2px solid #1a3a5c;margin-top:6px;">
                                TOTAL &nbsp; GHS <?php echo number_format($grand_total, 2); ?>
                            </div>
                        </div>

                        <!-- Payment Info -->
                        <div class="receipt-section">
                            <div class="receipt-section-title"><i class="fa fa-credit-card"></i> Payment Information</div>
                            <div class="receipt-row">
                                <span class="label">Method</span>
                                <span class="value"><?php echo htmlspecialchars($payment_method); ?></span>
                            </div>
                            <div class="receipt-row">
                                <span class="label">Date &amp; Time</span>
                                <span class="value"><?php echo date('D, d M Y H:i', strtotime($transaction_date)); ?></span>
                            </div>
                            <div class="receipt-row">
                                <span class="label">Cashier</span>
                                <span class="value"><?php echo htmlspecialchars($cashier_name); ?></span>
                            </div>
                            <?php if($notes): ?>
                            <div class="receipt-row">
                                <span class="label">Notes</span>
                                <span class="value"><?php echo htmlspecialchars($notes); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="receipt-footer">
                        <i class="fa fa-check-circle text-success"></i> Thank you for visiting <?php echo htmlspecialchars($hospitalName); ?>.
                        <?php if($hospitalPhone): ?>
                        &nbsp;|&nbsp; <i class="fa fa-phone"></i> <?php echo htmlspecialchars($hospitalPhone); ?>
                        <?php endif; ?>
                        <br>This receipt is computer-generated and valid without a signature.
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="action-bar">
                    <a href="<?php echo base_url()?>app/walkin/print_grouped_receipt/<?php echo $client_id; ?>/<?php echo urlencode($txn_ids_str); ?>" class="btn btn-primary" target="_blank">
                        <i class="fa fa-print"></i> Print Receipt
                    </a>
                    <a href="<?php echo base_url()?>app/walkin/add_transaction/<?php echo $client_id; ?>" class="btn btn-success">
                        <i class="fa fa-plus"></i> Add Another Service
                    </a>
                    <a href="<?php echo base_url()?>app/walkin/register" class="btn btn-info">
                        <i class="fa fa-user-plus"></i> New Walk-In
                    </a>
                    <a href="<?php echo base_url()?>app/walkin" class="btn btn-default">
                        <i class="fa fa-dashboard"></i> Dashboard
                    </a>
                </div>
            </div>
        </section>
    </aside>
</div>

<script src="<?php echo base_url()?>public/js/jquery.min.js"></script>
<script src="<?php echo base_url()?>public/js/bootstrap.min.js"></script>
<script src="<?php echo base_url()?>public/js/AdminLTE/app.js"></script>
</body>
</html>
