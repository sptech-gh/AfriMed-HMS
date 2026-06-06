<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Print Receipt — <?php echo htmlspecialchars($txn->receipt_number); ?></title>
    <style>
        @page{margin:15mm;}
        *{box-sizing:border-box;margin:0;padding:0;}
        body{font-family:'Arial',sans-serif;font-size:12px;color:#111;background:#fff;}
        .receipt{max-width:80mm;margin:0 auto;padding:4px;}
        .header{text-align:center;border-bottom:2px dashed #999;padding-bottom:10px;margin-bottom:10px;}
        .hospital-name{font-size:16px;font-weight:900;text-transform:uppercase;letter-spacing:.5px;}
        .hospital-sub{font-size:10px;color:#555;margin-top:2px;}
        .receipt-tag{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;margin-top:8px;background:#111;color:#fff;padding:3px 10px;display:inline-block;border-radius:3px;}
        .receipt-no{font-size:13px;font-weight:700;margin-top:6px;}
        .section{margin:10px 0;}
        .section-title{font-size:9px;font-weight:700;text-transform:uppercase;color:#777;letter-spacing:.5px;border-bottom:1px solid #ddd;padding-bottom:3px;margin-bottom:6px;}
        .row{display:flex;justify-content:space-between;margin-bottom:3px;font-size:11px;}
        .row .label{color:#555;width:40%;}
        .row .value{font-weight:600;text-align:right;word-break:break-word;width:58%;}
        .amount-box{border:2px solid #111;border-radius:4px;padding:8px 12px;margin:10px 0;text-align:center;}
        .amount-label{font-size:9px;text-transform:uppercase;letter-spacing:.5px;color:#555;}
        .amount-val{font-size:22px;font-weight:900;}
        .footer{text-align:center;border-top:2px dashed #999;padding-top:10px;margin-top:10px;font-size:10px;color:#555;}
        .badge{display:inline-block;border:1px solid #333;border-radius:3px;padding:1px 6px;font-weight:700;font-size:10px;}
        .status{font-weight:700;font-size:12px;}
        @media print {
            body{-webkit-print-color-adjust:exact;}
            .no-print{display:none!important;}
        }
    </style>
</head>
<body>
<?php
$hospitalName    = (isset($companyInfo) && isset($companyInfo->company_name) && trim((string)$companyInfo->company_name) !== '') ? trim((string)$companyInfo->company_name) : 'SBMC Hospital';
$hospitalAddress = (isset($companyInfo) && isset($companyInfo->address)) ? $companyInfo->address : '';
$hospitalPhone   = (isset($companyInfo) && isset($companyInfo->phone)) ? $companyInfo->phone : '';
?>

<div class="receipt">
    <div class="header">
        <div class="hospital-name"><?php echo htmlspecialchars($hospitalName); ?></div>
        <?php if($hospitalAddress): ?>
        <div class="hospital-sub"><?php echo htmlspecialchars($hospitalAddress); ?></div>
        <?php endif; ?>
        <?php if($hospitalPhone): ?>
        <div class="hospital-sub">Tel: <?php echo htmlspecialchars($hospitalPhone); ?></div>
        <?php endif; ?>
        <div class="receipt-tag">Walk-In Receipt</div>
        <div class="receipt-no"><?php echo htmlspecialchars($txn->receipt_number); ?></div>
        <div style="font-size:10px;color:#555;margin-top:3px;">
            <?php echo date('D, d M Y  H:i', strtotime($txn->transaction_date)); ?>
        </div>
    </div>

    <!-- Amount -->
    <div class="amount-box">
        <div class="amount-label">Amount Paid</div>
        <div class="amount-val">GHS <?php echo number_format((float)$txn->amount, 2); ?></div>
        <div class="status"><?php echo strtoupper($txn->payment_status); ?></div>
    </div>

    <!-- Client -->
    <div class="section">
        <div class="section-title">Client</div>
        <div class="row"><span class="label">Name</span><span class="value"><?php echo htmlspecialchars($txn->client_name); ?></span></div>
        <?php if($txn->phone): ?><div class="row"><span class="label">Phone</span><span class="value"><?php echo htmlspecialchars($txn->phone); ?></span></div><?php endif; ?>
        <?php if($txn->gender): ?><div class="row"><span class="label">Gender</span><span class="value"><?php echo htmlspecialchars($txn->gender); ?></span></div><?php endif; ?>
        <?php if($txn->referral): ?><div class="row"><span class="label">Referral</span><span class="value"><?php echo htmlspecialchars($txn->referral); ?></span></div><?php endif; ?>
    </div>

    <!-- Service -->
    <div class="section">
        <div class="section-title">Service</div>
        <div class="row"><span class="label">Type</span><span class="value"><span class="badge"><?php echo $txn->service_type; ?></span></span></div>
        <div class="row"><span class="label">Description</span><span class="value"><?php echo htmlspecialchars($txn->description); ?></span></div>
        <div class="row"><span class="label">Amount</span><span class="value">GHS <?php echo number_format((float)$txn->amount, 2); ?></span></div>

        <?php if ($txn->service_type === 'Pharmacy' && isset($txn->items) && is_array($txn->items) && count($txn->items) > 0): ?>
        <div class="section" style="margin-top:8px;">
            <div class="section-title">Items</div>
            <?php foreach ($txn->items as $it): ?>
                <div class="row">
                    <span class="label"><?php echo htmlspecialchars($it->drug_name ?: ('Drug #' . (int)$it->drug_id)); ?></span>
                    <span class="value">
                        <?php echo number_format((float)$it->qty, 2); ?> x <?php echo number_format((float)$it->unit_price, 2); ?>
                        = <?php echo number_format((float)$it->line_total, 2); ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Payment -->
    <div class="section">
        <div class="section-title">Payment</div>
        <div class="row"><span class="label">Method</span><span class="value"><?php echo htmlspecialchars($txn->payment_method); ?></span></div>
        <div class="row"><span class="label">Cashier</span><span class="value"><?php echo htmlspecialchars($txn->cashier_name ?: $txn->cashier_id); ?></span></div>
        <?php if($txn->notes): ?><div class="row"><span class="label">Notes</span><span class="value"><?php echo htmlspecialchars($txn->notes); ?></span></div><?php endif; ?>
    </div>

    <div class="footer">
        <strong>Thank you for visiting <?php echo htmlspecialchars($hospitalName); ?>!</strong><br>
        This receipt is computer-generated and valid without a signature.<br>
        <?php if($hospitalPhone): ?>Tel: <?php echo htmlspecialchars($hospitalPhone); ?><?php endif; ?>
    </div>
</div>

<!-- Print button — hidden when printing -->
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
