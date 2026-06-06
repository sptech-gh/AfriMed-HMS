<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Receipt #<?php echo isset($receipt) ? $receipt->receipt_no : ''; ?></title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; margin: 0; padding: 20px; }
        .receipt { max-width: 300px; margin: 0 auto; }
        .header { text-align: center; border-bottom: 1px dashed #000; padding-bottom: 10px; margin-bottom: 10px; }
        .header h2 { margin: 0 0 5px 0; font-size: 16px; }
        .header p { margin: 2px 0; }
        .details { margin-bottom: 15px; }
        .details p { margin: 3px 0; }
        .amount { text-align: center; font-size: 18px; font-weight: bold; border: 2px solid #000; padding: 10px; margin: 15px 0; }
        .footer { text-align: center; border-top: 1px dashed #000; padding-top: 10px; margin-top: 10px; font-size: 10px; }
        .row { display: flex; justify-content: space-between; }
        @media print {
            body { padding: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="text-align:center; margin-bottom:20px;">
        <button onclick="window.print();" style="padding:10px 30px; font-size:14px; cursor:pointer;">Print Receipt</button>
        <button onclick="window.close();" style="padding:10px 30px; font-size:14px; cursor:pointer; margin-left:10px;">Close</button>
    </div>

    <?php if (isset($receipt)) { ?>
    <div class="receipt">
        <div class="header">
            <h2><?php echo isset($companyInfo->company_name) ? strtoupper($companyInfo->company_name) : 'HOSPITAL'; ?></h2>
            <?php if (isset($companyInfo->address)) { ?><p><?php echo $companyInfo->address; ?></p><?php } ?>
            <?php if (isset($companyInfo->phone)) { ?><p>Tel: <?php echo $companyInfo->phone; ?></p><?php } ?>
            <p style="margin-top:10px;"><strong>PAYMENT RECEIPT</strong></p>
        </div>

        <div class="details">
            <div class="row">
                <span>Receipt #:</span>
                <span><strong><?php echo $receipt->receipt_no; ?></strong></span>
            </div>
            <div class="row">
                <span>Date:</span>
                <span><?php echo date('Y-m-d H:i', strtotime($receipt->dDate)); ?></span>
            </div>
            <div class="row">
                <span>Invoice #:</span>
                <span><?php echo $receipt->invoice_no; ?></span>
            </div>
            <hr style="border:none; border-top:1px dashed #ccc; margin:10px 0;">
            <p><strong>Patient:</strong> <?php echo $receipt->patient_name; ?></p>
            <p><strong>Patient ID:</strong> <?php echo $receipt->patient_no; ?></p>
            <?php if (isset($receipt->phone) && $receipt->phone) { ?>
            <p><strong>Phone:</strong> <?php echo $receipt->phone; ?></p>
            <?php } ?>
        </div>

        <div class="amount">
            GHS <?php echo number_format($receipt->total_amount, 2); ?>
        </div>

        <div class="details">
            <div class="row">
                <span>Payment Method:</span>
                <span><?php echo $receipt->payment_type; ?></span>
            </div>
            <div class="row">
                <span>Cashier:</span>
                <span><?php echo isset($receipt->cashier_fullname) && $receipt->cashier_fullname ? $receipt->cashier_fullname : $receipt->cashier_name; ?></span>
            </div>
        </div>

        <div class="footer">
            <p>Thank you for your payment!</p>
            <p>This is a computer-generated receipt.</p>
            <p>Printed: <?php echo date('Y-m-d H:i:s'); ?></p>
        </div>
    </div>
    <?php } else { ?>
    <div style="text-align:center; padding:50px;">
        <p>Receipt not found.</p>
    </div>
    <?php } ?>
</body>
</html>
