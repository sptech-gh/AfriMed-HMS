<!DOCTYPE html>
<html>
<head>
    <title>Invoice <?= $invoice->invoice_no ?></title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; margin: 20px; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { margin: 0; font-size: 18px; }
        .header p { margin: 5px 0; }
        .info-row { display: flex; justify-content: space-between; margin-bottom: 15px; }
        .info-box { width: 48%; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f5f5f5; }
        .text-right { text-align: right; }
        .total-row { font-weight: bold; background: #f9f9f9; }
        .footer { margin-top: 30px; text-align: center; font-size: 10px; }
        @media print { body { margin: 0; } }
    </style>
</head>
<body onload="window.print()">
    <div class="header">
        <h1><?= $hospital->hospital_name ?? 'Hospital Name' ?></h1>
        <p><?= $hospital->address ?? '' ?></p>
        <p>Tel: <?= $hospital->phone ?? '' ?></p>
        <hr>
        <h2>INVOICE</h2>
    </div>

    <div class="info-row">
        <div class="info-box">
            <strong>Bill To:</strong><br>
            <?= $patient->firstname ?> <?= $patient->lastname ?><br>
            Patient No: <?= $patient->patient_no ?><br>
            Phone: <?= $patient->phone ?>
        </div>
        <div class="info-box" style="text-align: right;">
            <strong>Invoice No:</strong> <?= $invoice->invoice_no ?><br>
            <strong>Date:</strong> <?= date('d/m/Y', strtotime($invoice->created_at)) ?><br>
            <strong>Visit ID:</strong> <?= $invoice->visit_id ?>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th>Qty</th>
                <th>Unit Price</th>
                <th>Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($invoice->items as $item): ?>
            <tr>
                <td><?= $item->charge_name ?></td>
                <td><?= $item->quantity ?></td>
                <td class="text-right"><?= number_format($item->unit_price, 2) ?></td>
                <td class="text-right"><?= number_format($item->patient_amount, 2) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr><td colspan="3" class="text-right">Subtotal:</td><td class="text-right"><?= number_format($invoice->subtotal, 2) ?></td></tr>
            <?php if ($invoice->total_discount > 0): ?>
            <tr><td colspan="3" class="text-right">Discount:</td><td class="text-right">-<?= number_format($invoice->total_discount, 2) ?></td></tr>
            <?php endif; ?>
            <?php if ($invoice->nhis_amount > 0): ?>
            <tr><td colspan="3" class="text-right">NHIS Coverage:</td><td class="text-right">-<?= number_format($invoice->nhis_amount, 2) ?></td></tr>
            <?php endif; ?>
            <tr class="total-row"><td colspan="3" class="text-right">TOTAL DUE:</td><td class="text-right">GHS <?= number_format($invoice->patient_amount, 2) ?></td></tr>
            <tr><td colspan="3" class="text-right">Amount Paid:</td><td class="text-right">GHS <?= number_format($invoice->amount_paid, 2) ?></td></tr>
            <tr class="total-row"><td colspan="3" class="text-right">BALANCE:</td><td class="text-right">GHS <?= number_format($invoice->amount_outstanding, 2) ?></td></tr>
        </tfoot>
    </table>

    <div class="footer">
        <p>Thank you for choosing <?= $hospital->hospital_name ?? 'our hospital' ?>.</p>
        <p>Printed: <?= date('d/m/Y H:i:s') ?></p>
    </div>
</body>
</html>
