<?php if (!defined('BASEPATH')) exit('No direct script access allowed');
/*
 * thermal_final_receipt.php
 * Final grouped patient receipt showing items by department with dispatch statuses.
 */
$hospitalName    = (isset($companyInfo) && isset($companyInfo->company_name) && trim((string)$companyInfo->company_name) !== '') ? trim((string)$companyInfo->company_name) : 'SBMC Hospital';
$hospitalAddress = (isset($companyInfo) && isset($companyInfo->company_address)) ? $companyInfo->company_address : '';
$hospitalPhone   = (isset($companyInfo) && isset($companyInfo->company_contactNo)) ? $companyInfo->company_contactNo : '';
$hospitalTIN     = (isset($companyInfo) && isset($companyInfo->TIN)) ? $companyInfo->TIN : '';

$invoice_no = $invoice->invoice_no;
$patientName = isset($patientInfo->name) ? $patientInfo->name : 'Walk-in Client';
$patientNo = isset($patientInfo->patient_no) ? $patientInfo->patient_no : (isset($invoice->patient_no) ? $invoice->patient_no : '');

// Group items by normalized department
$groups = array();
foreach ($items as $item) {
    $raw_mod = isset($item->source_module) ? strtoupper(trim((string)$item->source_module)) : '';
    if ($raw_mod === '') {
        $bill_name_lower = strtolower($item->bill_name);
        if (strpos($bill_name_lower, 'lab') !== false || strpos($bill_name_lower, 'test') !== false) {
            $raw_mod = 'LABORATORY';
        } elseif (strpos($bill_name_lower, 'drug') !== false || strpos($bill_name_lower, 'tablet') !== false || strpos($bill_name_lower, 'syrup') !== false || strpos($bill_name_lower, 'capsule') !== false) {
            $raw_mod = 'PHARMACY';
        } elseif (strpos($bill_name_lower, 'scan') !== false || strpos($bill_name_lower, 'ultrasound') !== false || strpos($bill_name_lower, 'sono') !== false) {
            $raw_mod = 'SONOGRAPHY';
        } elseif (strpos($bill_name_lower, 'xray') !== false || strpos($bill_name_lower, 'x-ray') !== false || strpos($bill_name_lower, 'radiology') !== false) {
            $raw_mod = 'RADIOLOGY';
        } elseif (strpos($bill_name_lower, 'procedure') !== false) {
            $raw_mod = 'PROCEDURE';
        } elseif (strpos($bill_name_lower, 'consultation') !== false || strpos($bill_name_lower, 'opd fee') !== false) {
            $raw_mod = 'CONSULTATION';
        } else {
            $raw_mod = 'OTHER';
        }
    }
    
    $dept = 'OTHER';
    if (in_array($raw_mod, array('LAB', 'LABORATORY'))) {
        $dept = 'LABORATORY';
    } elseif ($raw_mod === 'PHARMACY') {
        $dept = 'PHARMACY';
    } elseif ($raw_mod === 'SONOGRAPHY') {
        $dept = 'SONOGRAPHY';
    } elseif ($raw_mod === 'RADIOLOGY') {
        $dept = 'RADIOLOGY';
    } elseif ($raw_mod === 'PROCEDURE') {
        $dept = 'PROCEDURE';
    } elseif ($raw_mod === 'CONSULTATION') {
        $dept = 'CONSULTATION';
    }
    
    $groups[$dept][] = $item;
}

// Map notifications by department for status badges
$notifMap = array();
foreach ($notifications as $n) {
    $notifMap[$n->department] = $n;
}

// Payment details
$total_due = (float)$invoice->total_amount - (float)$invoice->discount;
$total_paid = (float)$invoice->amount_paid;
$outstanding_balance = (float)$invoice->balance;
$payment_status = ($outstanding_balance <= 0.005) ? 'PAID' : 'PARTIALLY PAID';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Final Receipt — <?php echo htmlspecialchars($invoice_no); ?></title>
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
        .grand-status{font-weight:700;font-size:11px;}
        .status-PAID{color:#16a34a;}
        .status-PARTIALLY-PAID{color:#d97706;}
        
        /* sections */
        .section{margin:7px 0;}
        .section-title{font-size:8px;font-weight:700;text-transform:uppercase;color:#777;letter-spacing:.5px;border-bottom:1px solid #ddd;padding-bottom:2px;margin-bottom:4px;}
        .row2{display:flex;justify-content:space-between;margin-bottom:2px;font-size:10px;}
        .row2 .label{color:#555;width:40%;}
        .row2 .value{font-weight:600;text-align:right;word-break:break-word;width:58%;}
        
        /* service block */
        .svc-block{border:1px solid #ddd;border-radius:4px;padding:6px;margin-bottom:6px;background:#fafafa;}
        .svc-block-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;border-bottom:1px solid #eee;padding-bottom:2px;}
        .svc-title{font-weight:800;font-size:10px;color:#1a6fa5;}
        .svc-status{font-size:8px;font-weight:700;padding:1px 4px;border-radius:3px;}
        .status-badge-PENDING{background:#fef3c7;color:#92400e;border:1px solid #f59e0b;}
        .status-badge-DISPATCHED{background:#d1fae5;color:#065f46;border:1px solid #10b981;}
        .status-badge-NONE{background:#f3f4f6;color:#374151;border:1px solid #d1d5db;}
        
        /* items table */
        table.it{width:100%;border-collapse:collapse;font-size:9px;margin-top:3px;}
        table.it td{padding:2px;border-bottom:1px dotted #eee;}
        table.it td.r{text-align:right;}
        
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
        <div class="receipt-tag">Final Patient Receipt</div>
        <div class="receipt-no"><?php echo htmlspecialchars($invoice_no); ?></div>
        <div style="font-size:9px;color:#555;margin-top:3px;"><?php echo date('D, d M Y  H:i', strtotime($invoice->dDate)); ?></div>
    </div>

    <!-- Payment Status Box -->
    <div class="grand-box">
        <div class="grand-label">Invoice Balance Due</div>
        <div class="grand-val">GHS <?php echo number_format($outstanding_balance, 2); ?></div>
        <div class="grand-status status-<?php echo str_replace(' ', '-', $payment_status); ?>">
            <?php echo $payment_status; ?>
        </div>
    </div>

    <!-- Client -->
    <div class="section">
        <div class="section-title">Patient Info</div>
        <div class="row2"><span class="label">Patient Name</span><span class="value"><?php echo htmlspecialchars($patientName); ?></span></div>
        <?php if($patientNo): ?><div class="row2"><span class="label">Patient ID</span><span class="value"><?php echo htmlspecialchars($patientNo); ?></span></div><?php endif; ?>
        <?php if(isset($patientInfo->phone_no) && $patientInfo->phone_no): ?>
            <div class="row2"><span class="label">Phone</span><span class="value"><?php echo htmlspecialchars($patientInfo->phone_no); ?></span></div>
        <?php endif; ?>
    </div>

    <!-- Services Grouped with Status -->
    <div class="section">
        <div class="section-title">Department Cleared Services</div>

        <?php foreach ($groups as $dept => $itemList): 
            $status = 'NONE';
            if (isset($notifMap[$dept])) {
                $status = $notifMap[$dept]->status;
            }
            ?>
            <div class="svc-block">
                <div class="svc-block-header">
                    <span class="svc-title"><?php echo htmlspecialchars($dept); ?></span>
                    <span class="svc-status status-badge-<?php echo $status; ?>">
                        <?php echo ($status === 'NONE') ? 'NOT NOTIFIED' : (($status === 'PENDING') ? 'CLEARED / PENDING DEPT' : 'PROCESSED'); ?>
                    </span>
                </div>
                <table class="it">
                    <tbody>
                        <?php foreach($itemList as $item): ?>
                        <tr>
                            <td style="width: 70%;"><?php echo htmlspecialchars($item->bill_name); ?></td>
                            <td class="r"><?php echo number_format((float)$item->qty, 2); ?> x <?php echo number_format((float)$item->rate, 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Summary Details -->
    <div class="section">
        <div class="section-title">Financial Summary</div>
        <div class="row2"><span class="label">Subtotal</span><span class="value">GHS <?php echo number_format($invoice->sub_total, 2); ?></span></div>
        <?php if((float)$invoice->discount > 0): ?>
            <div class="row2"><span class="label">Discount</span><span class="value">GHS <?php echo number_format($invoice->discount, 2); ?></span></div>
        <?php endif; ?>
        <div class="row2"><span class="label">Total Due</span><span class="value">GHS <?php echo number_format($total_due, 2); ?></span></div>
        <div class="row2"><span class="label">Total Paid to Date</span><span class="value">GHS <?php echo number_format($total_paid, 2); ?></span></div>
        <div class="row2"><span class="label">Remaining Balance</span><span class="value"><strong>GHS <?php echo number_format($outstanding_balance, 2); ?></strong></span></div>
    </div>

    <!-- Footer -->
    <div class="footer">
        <strong>Thank you for visiting <?php echo htmlspecialchars($hospitalName); ?>!</strong><br>
        Please retain this final receipt for your records.<br>
        Printed: <?php echo date('Y-m-d H:i:s'); ?>
    </div>
</div>

<!-- Print/Close Buttons -->
<div class="no-print" style="text-align:center;margin:20px;">
    <button onclick="window.print();" style="padding:10px 30px;font-size:14px;background:#1a6fa5;color:#fff;border:none;border-radius:6px;cursor:pointer;font-weight:700;">
        &#128424; Print Final Receipt
    </button>
    <button onclick="window.close();" style="padding:10px 20px;font-size:14px;background:#6c757d;color:#fff;border:none;border-radius:6px;cursor:pointer;margin-left:10px;">
        Close
    </button>
</div>
<script>window.onload = function(){ window.print(); };</script>
</body>
</html>
