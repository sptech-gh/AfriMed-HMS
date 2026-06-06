<!DOCTYPE html>
<html>
<head>
    <title>Receipt <?= $payment->receipt_no ?></title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; margin: 20px; width: 300px; }
        .header { text-align: center; margin-bottom: 15px; }
        .header h1 { margin: 0; font-size: 16px; }
        .divider { border-top: 1px dashed #000; margin: 10px 0; }
        .row { display: flex; justify-content: space-between; margin: 5px 0; }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        .large { font-size: 16px; }
        @media print { body { margin: 0; } }
    </style>
</head>
<body onload="window.print()">
    <div class="header">
        <h1><?= $hospital->hospital_name ?? 'Hospital Name' ?></h1>
        <p><?= $hospital->address ?? '' ?></p>
        <p>Tel: <?= $hospital->phone ?? '' ?></p>
    </div>
    
    <div class="divider"></div>
    <div class="center bold">PAYMENT RECEIPT</div>
    <div class="divider"></div>

    <div class="row"><span>Receipt No:</span><span class="bold"><?= $payment->receipt_no ?></span></div>
    <div class="row"><span>Date:</span><span><?= date('d/m/Y H:i', strtotime($payment->payment_date)) ?></span></div>
    <div class="row"><span>Invoice:</span><span><?= $payment->invoice_no ?></span></div>
    
    <div class="divider"></div>
    
    <div class="row"><span>Patient:</span><span><?= $patient->firstname ?> <?= $patient->lastname ?></span></div>
    <div class="row"><span>Patient No:</span><span><?= $patient->patient_no ?></span></div>
    
    <div class="divider"></div>
    
    <div class="row"><span>Payment Method:</span><span><?= $payment->payment_method ?></span></div>
    <?php if ($payment->payment_reference): ?>
    <div class="row"><span>Reference:</span><span><?= $payment->payment_reference ?></span></div>
    <?php endif; ?>
    
    <div class="divider"></div>
    
    <div class="row large bold">
        <span>AMOUNT PAID:</span>
        <span>GHS <?= number_format($payment->payment_amount, 2) ?></span>
    </div>
    
    <div class="divider"></div>
    
    <div class="center">
        <p>Thank you for your payment!</p>
        <p style="font-size: 10px;">Printed: <?= date('d/m/Y H:i:s') ?></p>
    </div>
</body>
</html>
