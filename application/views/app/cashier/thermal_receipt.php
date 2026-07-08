<?php if (!defined('BASEPATH')) exit('No direct script access allowed');
/*
 * thermal_receipt.php
 * Compact thermal receipt for cashier payment.
 */
$hospitalName    = (isset($companyInfo) && isset($companyInfo->company_name) && trim((string)$companyInfo->company_name) !== '') ? trim((string)$companyInfo->company_name) : 'SBMC Hospital';
$hospitalAddress = (isset($companyInfo) && isset($companyInfo->company_address)) ? $companyInfo->company_address : '';
$hospitalPhone   = (isset($companyInfo) && isset($companyInfo->company_contactNo)) ? $companyInfo->company_contactNo : '';
$hospitalTIN     = (isset($companyInfo) && isset($companyInfo->TIN)) ? $companyInfo->TIN : '';

$receipt_no = $getOR ? $getOR->receipt_no : '';
$invoice_no = $headerInv ? $headerInv->invoice_no : '';
$transaction_date = ($getOR && !empty($getOR->dDate)) ? $getOR->dDate : date('Y-m-d H:i:s');
$cashier_name = !empty($receipt_cashier_name) ? $receipt_cashier_name : $receipt_cashier_id;
$payment_method = !empty($receipt_payment_method_label) ? $receipt_payment_method_label : '';

$patientName = isset($patientInfo->name) ? $patientInfo->name : 'Walk-in Client';
$patientNo = isset($patientInfo->patient_no) ? $patientInfo->patient_no : (isset($getOR->patient_no) ? $getOR->patient_no : '');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Receipt #<?php echo htmlspecialchars($receipt_no); ?></title>
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
        
        /* grand total box */
        .grand-box{border:2px solid #111;border-radius:3px;padding:6px 10px;margin:8px 0;text-align:center;}
        .grand-label{font-size:8px;text-transform:uppercase;letter-spacing:.5px;color:#555;}
        .grand-val{font-size:20px;font-weight:900;}
        .grand-status{font-weight:700;font-size:11px;color:#16a34a;}
        
        /* sections */
        .section{margin:7px 0;}
        .section-title{font-size:8px;font-weight:700;text-transform:uppercase;color:#777;letter-spacing:.5px;border-bottom:1px solid #ddd;padding-bottom:2px;margin-bottom:4px;}
        .row2{display:flex;justify-content:space-between;margin-bottom:2px;font-size:10px;}
        .row2 .label{color:#555;width:40%;}
        .row2 .value{font-weight:600;text-align:right;word-break:break-word;width:58%;}
        
        /* items table */
        table.it{width:100%;border-collapse:collapse;font-size:9px;margin-top:5px;margin-bottom:5px;}
        table.it th{font-weight:700;border-bottom:1px solid #ccc;padding:2px 3px;text-align:left;}
        table.it th.r, table.it td.r{text-align:right;}
        table.it td{padding:3px 3px;border-bottom:1px dotted #eee;vertical-align:top;}
        
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
        <?php if($hospitalTIN):     ?><div class="hospital-sub">TIN: <?php echo htmlspecialchars($hospitalTIN); ?></div><?php endif; ?>
        <div class="receipt-tag">Payment Receipt</div>
        <div class="receipt-no"><?php echo htmlspecialchars($receipt_no); ?></div>
        <div style="font-size:9px;color:#555;margin-top:3px;"><?php echo date('D, d M Y  H:i', strtotime($transaction_date)); ?></div>
    </div>

    <!-- Amount Paid -->
    <div class="grand-box">
        <div class="grand-label">Amount Paid</div>
        <div class="grand-val">GHS <?php echo number_format($receipt_payment, 2); ?></div>
        <div class="grand-status">PAID</div>
    </div>

    <!-- Client -->
    <div class="section">
        <div class="section-title">Patient Info</div>
        <div class="row2"><span class="label">Name</span><span class="value"><?php echo htmlspecialchars($patientName); ?></span></div>
        <?php if($patientNo): ?><div class="row2"><span class="label">Patient ID</span><span class="value"><?php echo htmlspecialchars($patientNo); ?></span></div><?php endif; ?>
        <?php if(isset($patientInfo->phone_no) && $patientInfo->phone_no): ?>
            <div class="row2"><span class="label">Phone</span><span class="value"><?php echo htmlspecialchars($patientInfo->phone_no); ?></span></div>
        <?php endif; ?>
        <div class="row2"><span class="label">Invoice No</span><span class="value"><?php echo htmlspecialchars($invoice_no); ?></span></div>
    </div>

    <!-- Items Billed -->
    <div class="section">
        <div class="section-title">Billed Items</div>
        <table class="it">
            <thead>
                <tr>
                    <th>Item</th>
                    <th class="r">Qty</th>
                    <th class="r">Rate</th>
                    <th class="r">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($detailsInv as $item): ?>
                <tr>
                    <td>
                        <div style="font-weight:700;"><?php echo htmlspecialchars($item->bill_name); ?></div>
                        <?php if (isset($item->note) && trim((string)$item->note) !== ''): ?>
                            <div style="font-size:8px;color:#555;font-style:italic;"><?php echo htmlspecialchars($item->note); ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="r"><?php echo number_format((float)$item->qty, 2); ?></td>
                    <td class="r"><?php echo number_format((float)$item->rate, 2); ?></td>
                    <td class="r"><?php echo number_format((float)$item->amount, 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Amount and Payment Summary -->
    <div class="section">
        <div class="section-title">Amount Summary</div>
        <div class="row2"><span class="label">Subtotal</span><span class="value">GHS <?php echo number_format($headerInv->sub_total, 2); ?></span></div>
        <?php if((float)$headerInv->discount > 0): ?>
            <div class="row2"><span class="label">Discount</span><span class="value">GHS <?php echo number_format($headerInv->discount, 2); ?></span></div>
        <?php endif; ?>
        <div class="row2"><span class="label">Invoice Total</span><span class="value">GHS <?php echo number_format($headerInv->total_amount, 2); ?></span></div>
        
        <hr style="border:none; border-top:1px dotted #ccc; margin:4px 0;">
        
        <div class="row2"><span class="label">Previous Paid</span><span class="value">GHS <?php echo number_format((float)$receipt_prev_balance, 2); ?></span></div>
        <div class="row2"><span class="label">This Payment</span><span class="value"><strong>GHS <?php echo number_format($receipt_payment, 2); ?></strong></span></div>
        <div class="row2"><span class="label">Total Paid to Date</span><span class="value">GHS <?php echo number_format($receipt_total_paid, 2); ?></span></div>
        <div class="row2"><span class="label">Outstanding Balance</span><span class="value">GHS <?php echo number_format($receipt_outstanding_balance, 2); ?></span></div>
        
        <hr style="border:none; border-top:1px dotted #ccc; margin:4px 0;">
        
        <div class="row2"><span class="label">Payment Method</span><span class="value"><?php echo htmlspecialchars($payment_method); ?></span></div>
        <div class="row2"><span class="label">Cashier</span><span class="value"><?php echo htmlspecialchars($cashier_name); ?></span></div>
    </div>

    <!-- Footer -->
    <div class="footer">
        <strong>Thank you for your payment!</strong><br>
        This receipt is computer-generated and valid without a signature.<br>
        Printed: <?php echo date('Y-m-d H:i:s'); ?>
    </div>
</div>

<!-- Print/Close Buttons -->
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
