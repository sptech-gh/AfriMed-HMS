<?php if (!defined('BASEPATH')) exit('No direct script access allowed');
/*
 * print_grouped_receipt.php
 * Thermal-printer-friendly (80mm) walk-in grouped receipt.
 * Variables: $client, $transactions (array), $companyInfo, $txn_ids_str
 */

$hospitalName    = (isset($companyInfo) && isset($companyInfo->company_name) && trim((string)$companyInfo->company_name) !== '') ? trim((string)$companyInfo->company_name) : 'SBMC Hospital';
$hospitalAddress = (isset($companyInfo) && isset($companyInfo->address)) ? $companyInfo->address : '';
$hospitalPhone   = (isset($companyInfo) && isset($companyInfo->phone))   ? $companyInfo->phone   : '';

$grand_total    = 0.0;
$payment_method = '';
$payment_status = '';
$cashier_name   = '';
$transaction_date = '';
$notes          = '';
$receipt_numbers = array();
foreach ($transactions as $txn) {
    $grand_total += (float)$txn->amount;
    if ($payment_method === '')   $payment_method   = $txn->payment_method;
    if ($payment_status === '')   $payment_status   = $txn->payment_status;
    if ($cashier_name   === '')   $cashier_name     = $txn->cashier_name ?: $txn->cashier_id;
    if ($transaction_date === '') $transaction_date = $txn->transaction_date;
    if ($notes === '' && !empty($txn->notes)) $notes = $txn->notes;
    $receipt_numbers[] = $txn->receipt_number;
}
$primary_receipt = isset($receipt_numbers[0]) ? $receipt_numbers[0] : 'N/A';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Print Receipt — <?php echo htmlspecialchars($primary_receipt); ?></title>
    <style>
        @page{margin:8mm;}
        *{box-sizing:border-box;margin:0;padding:0;}
        body{font-family:'Arial',sans-serif;font-size:11px;color:#111;background:#fff;}
        .receipt{max-width:80mm;margin:0 auto;padding:4px;}
        .header{text-align:center;border-bottom:2px dashed #999;padding-bottom:8px;margin-bottom:8px;}
        .hospital-name{font-size:15px;font-weight:900;text-transform:uppercase;letter-spacing:.5px;}
        .hospital-sub{font-size:9px;color:#555;margin-top:2px;}
        .receipt-tag{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:1px;margin-top:7px;background:#111;color:#fff;padding:3px 10px;display:inline-block;border-radius:3px;}
        .receipt-no{font-size:12px;font-weight:700;margin-top:5px;}
        /* grand total */
        .grand-box{border:2px solid #111;border-radius:3px;padding:6px 10px;margin:8px 0;text-align:center;}
        .grand-label{font-size:8px;text-transform:uppercase;letter-spacing:.5px;color:#555;}
        .grand-val{font-size:20px;font-weight:900;}
        .grand-status{font-weight:700;font-size:11px;}
        /* sections */
        .section{margin:7px 0;}
        .section-title{font-size:8px;font-weight:700;text-transform:uppercase;color:#777;letter-spacing:.5px;border-bottom:1px solid #ddd;padding-bottom:2px;margin-bottom:4px;}
        .row2{display:flex;justify-content:space-between;margin-bottom:2px;font-size:10px;}
        .row2 .label{color:#555;width:40%;}
        .row2 .value{font-weight:600;text-align:right;word-break:break-word;width:58%;}
        /* service block */
        .svc-block{border:1px solid #ddd;border-radius:3px;padding:5px 7px;margin-bottom:6px;}
        .svc-block-header{display:flex;justify-content:space-between;margin-bottom:4px;font-size:10px;}
        .svc-type{font-weight:700;}
        .svc-sub{font-weight:700;}
        table.it{width:100%;border-collapse:collapse;font-size:9px;}
        table.it th{font-weight:700;border-bottom:1px solid #ccc;padding:2px 3px;text-align:left;}
        table.it th.r, table.it td.r{text-align:right;}
        table.it td{padding:2px 3px;border-bottom:1px dotted #eee;}
        .grand-line{text-align:right;font-size:12px;font-weight:900;border-top:2px solid #111;padding-top:4px;margin-top:4px;}
        /* footer */
        .footer{text-align:center;border-top:2px dashed #999;padding-top:7px;margin-top:7px;font-size:9px;color:#555;}
        @media print{
            body{-webkit-print-color-adjust:exact;}
            .no-print{display:none!important;}
        }
    </style>
</head>
<body>

<div class="receipt">
    <!-- Header -->
    <div class="header">
        <div class="hospital-name"><?php echo htmlspecialchars($hospitalName); ?></div>
        <?php if($hospitalAddress): ?><div class="hospital-sub"><?php echo htmlspecialchars($hospitalAddress); ?></div><?php endif; ?>
        <?php if($hospitalPhone):   ?><div class="hospital-sub">Tel: <?php echo htmlspecialchars($hospitalPhone); ?></div><?php endif; ?>
        <div class="receipt-tag">Walk-In Receipt</div>
        <div class="receipt-no"><?php echo htmlspecialchars($primary_receipt); ?></div>
        <?php if(count($receipt_numbers) > 1): ?>
        <div style="font-size:8px;color:#777;margin-top:2px;"><?php echo implode(' / ', array_slice($receipt_numbers, 1)); ?></div>
        <?php endif; ?>
        <div style="font-size:9px;color:#555;margin-top:3px;"><?php echo date('D, d M Y  H:i', strtotime($transaction_date)); ?></div>
    </div>

    <!-- Grand Total -->
    <div class="grand-box">
        <div class="grand-label">Total Amount</div>
        <div class="grand-val">GHS <?php echo number_format($grand_total, 2); ?></div>
        <div class="grand-status"><?php echo strtoupper($payment_status); ?></div>
    </div>

    <!-- Client -->
    <div class="section">
        <div class="section-title">Client</div>
        <div class="row2"><span class="label">Name</span><span class="value"><?php echo htmlspecialchars($client->client_name); ?></span></div>
        <?php if($client->phone): ?><div class="row2"><span class="label">Phone</span><span class="value"><?php echo htmlspecialchars($client->phone); ?></span></div><?php endif; ?>
        <?php if($client->gender): ?><div class="row2"><span class="label">Gender</span><span class="value"><?php echo htmlspecialchars($client->gender); ?></span></div><?php endif; ?>
        <?php if(!empty($client->referral)): ?><div class="row2"><span class="label">Referral</span><span class="value"><?php echo htmlspecialchars($client->referral); ?></span></div><?php endif; ?>
    </div>

    <!-- Services -->
    <div class="section">
        <div class="section-title">Services</div>

        <?php foreach ($transactions as $txn): ?>
        <div class="svc-block">
            <div class="svc-block-header">
                <span class="svc-type"><?php echo htmlspecialchars($txn->service_type); ?></span>
                <span class="svc-sub">GHS <?php echo number_format((float)$txn->amount, 2); ?></span>
            </div>

            <?php if ($txn->service_type === 'Pharmacy' && isset($txn->items) && is_array($txn->items) && count($txn->items) > 0): ?>
                <table class="it">
                    <thead><tr><th>Drug</th><th class="r">Qty</th><th class="r">Unit</th><th class="r">Line</th></tr></thead>
                    <tbody>
                    <?php foreach ($txn->items as $it): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($it->drug_name ?: ('Drug #'.(int)$it->drug_id)); ?></td>
                            <td class="r"><?php echo number_format((float)$it->qty, 2); ?></td>
                            <td class="r"><?php echo number_format((float)$it->unit_price, 2); ?></td>
                            <td class="r"><?php echo number_format((float)$it->line_total, 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php elseif ($txn->service_type !== 'Pharmacy' && isset($txn->service_items) && is_array($txn->service_items) && count($txn->service_items) > 0): ?>
                <table class="it">
                    <thead><tr><th>Item</th><th class="r">Qty</th><th class="r">Unit</th><th class="r">Line</th></tr></thead>
                    <tbody>
                    <?php foreach ($txn->service_items as $it): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($it->item_name); ?></td>
                            <td class="r"><?php echo number_format((float)$it->qty, 2); ?></td>
                            <td class="r"><?php echo number_format((float)$it->unit_price, 2); ?></td>
                            <td class="r"><?php echo number_format((float)$it->line_total, 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div style="font-size:9px;color:#555;"><?php echo htmlspecialchars($txn->description); ?></div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>

        <div class="grand-line">TOTAL: GHS <?php echo number_format($grand_total, 2); ?></div>
    </div>

    <!-- Payment -->
    <div class="section">
        <div class="section-title">Payment</div>
        <div class="row2"><span class="label">Method</span><span class="value"><?php echo htmlspecialchars($payment_method); ?></span></div>
        <div class="row2"><span class="label">Cashier</span><span class="value"><?php echo htmlspecialchars($cashier_name); ?></span></div>
        <?php if($notes): ?><div class="row2"><span class="label">Notes</span><span class="value"><?php echo htmlspecialchars($notes); ?></span></div><?php endif; ?>
    </div>

    <!-- Footer -->
    <div class="footer">
        <strong>Thank you for visiting <?php echo htmlspecialchars($hospitalName); ?>!</strong><br>
        This receipt is computer-generated and valid without a signature.<br>
        <?php if($hospitalPhone): ?>Tel: <?php echo htmlspecialchars($hospitalPhone); ?><?php endif; ?>
    </div>
</div>

<!-- Print button — hidden on print -->
<div class="no-print" style="text-align:center;margin:20px;">
    <button onclick="window.print();" style="padding:10px 30px;font-size:14px;background:#1a6fa5;color:#fff;border:none;border-radius:6px;cursor:pointer;font-weight:700;">
        &#128424; Print
    </button>
    <button onclick="window.close();" style="padding:10px 20px;font-size:14px;background:#6c757d;color:#fff;border:none;border-radius:6px;cursor:pointer;margin-left:10px;">
        Close
    </button>
</div>
<script>window.onload = function(){ window.print(); };</script>
</body>
</html>
