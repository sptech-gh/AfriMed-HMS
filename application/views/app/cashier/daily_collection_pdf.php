<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Daily Collection Report</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { margin: 0; font-size: 18px; }
        .header h2 { margin: 5px 0; font-size: 14px; font-weight: normal; }
        .summary { margin-bottom: 20px; }
        .summary-box { display: inline-block; width: 30%; text-align: center; border: 1px solid #ccc; padding: 10px; margin-right: 2%; }
        .summary-box h3 { margin: 0; font-size: 20px; }
        .summary-box p { margin: 5px 0 0 0; font-size: 10px; color: #666; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        th, td { border: 1px solid #ccc; padding: 5px; text-align: left; }
        th { background-color: #f5f5f5; }
        .text-right { text-align: right; }
        .total-row { background-color: #e8f5e9; font-weight: bold; }
        .footer { margin-top: 30px; font-size: 10px; color: #666; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h1><?php echo isset($companyInfo->company_name) ? $companyInfo->company_name : 'Hospital'; ?></h1>
        <h2>Daily Collection Report</h2>
        <p><strong>Date:</strong> <?php echo isset($date) ? date('l, F d, Y', strtotime($date)) : date('l, F d, Y'); ?></p>
    </div>

    <div class="summary">
        <table>
            <tr>
                <td style="text-align:center; width:33%">
                    <strong style="font-size:18px">GHS <?php echo number_format(isset($summary->total) ? $summary->total : 0, 2); ?></strong><br>
                    <small>Total Collections</small>
                </td>
                <td style="text-align:center; width:33%">
                    <strong style="font-size:18px"><?php echo isset($summary->count) ? $summary->count : 0; ?></strong><br>
                    <small>Transactions</small>
                </td>
                <td style="text-align:center; width:33%">
                    <strong style="font-size:18px">GHS <?php echo (isset($summary->count) && $summary->count > 0) ? number_format($summary->total / $summary->count, 2) : '0.00'; ?></strong><br>
                    <small>Average Transaction</small>
                </td>
            </tr>
        </table>
    </div>

    <h3>Collections by Payment Method</h3>
    <table>
        <thead>
            <tr>
                <th>Payment Method</th>
                <th class="text-right">Count</th>
                <th class="text-right">Amount (GHS)</th>
            </tr>
        </thead>
        <tbody>
            <?php if (isset($by_method) && count($by_method) > 0) { ?>
                <?php foreach ($by_method as $m) { ?>
                <tr>
                    <td><?php echo $m->payment_type; ?></td>
                    <td class="text-right"><?php echo $m->count; ?></td>
                    <td class="text-right"><?php echo number_format($m->total, 2); ?></td>
                </tr>
                <?php } ?>
            <?php } else { ?>
                <tr><td colspan="3" style="text-align:center">No data</td></tr>
            <?php } ?>
        </tbody>
    </table>

    <h3>Transaction Details</h3>
    <table>
        <thead>
            <tr>
                <th>Receipt #</th>
                <th>Time</th>
                <th>Invoice #</th>
                <th>Patient</th>
                <th>Method</th>
                <th class="text-right">Amount</th>
                <th>Cashier</th>
            </tr>
        </thead>
        <tbody>
            <?php if (isset($collections) && count($collections) > 0) { ?>
                <?php foreach ($collections as $c) { ?>
                <tr>
                    <td><?php echo $c->receipt_no; ?></td>
                    <td><?php echo date('H:i', strtotime($c->dDate)); ?></td>
                    <td><?php echo $c->invoice_no; ?></td>
                    <td><?php echo isset($c->patient_name) ? $c->patient_name : $c->patient_no; ?></td>
                    <td><?php echo $c->payment_type; ?></td>
                    <td class="text-right"><?php echo number_format($c->amountPaid, 2); ?></td>
                    <td><?php echo isset($c->cashier_name) ? $c->cashier_name : '-'; ?></td>
                </tr>
                <?php } ?>
                <tr class="total-row">
                    <td colspan="5" class="text-right"><strong>TOTAL:</strong></td>
                    <td class="text-right"><strong>GHS <?php echo number_format(isset($summary->total) ? $summary->total : 0, 2); ?></strong></td>
                    <td></td>
                </tr>
            <?php } else { ?>
                <tr><td colspan="7" style="text-align:center">No transactions for this date</td></tr>
            <?php } ?>
        </tbody>
    </table>

    <div class="footer">
        <p>Generated: <?php echo date('Y-m-d H:i:s'); ?></p>
    </div>
</body>
</html>
