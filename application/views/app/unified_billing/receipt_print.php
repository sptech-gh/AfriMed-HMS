<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Receipt <?php echo $payment->payment_no; ?></title>
    <link href="<?php echo base_url(); ?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css">
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        .receipt { width: 300px; margin: 0 auto; padding: 20px; }
        .receipt-header { text-align: center; border-bottom: 2px dashed #000; padding-bottom: 10px; margin-bottom: 10px; }
        .receipt-header h2 { margin: 5px 0; font-size: 18px; }
        .receipt-header p { margin: 2px 0; font-size: 11px; }
        .receipt-body { margin: 15px 0; }
        .receipt-row { display: flex; justify-content: space-between; margin: 5px 0; }
        .receipt-row .label { font-weight: bold; }
        .receipt-footer { border-top: 2px dashed #000; padding-top: 10px; margin-top: 15px; text-align: center; }
        .amount { font-size: 20px; font-weight: bold; text-align: center; margin: 15px 0; }
        .barcode { text-align: center; margin: 15px 0; font-family: monospace; font-size: 14px; }
        .no-print { margin-top: 20px; text-align: center; }
        @media print {
            .no-print { display: none; }
            body { margin: 0; }
        }
    </style>
</head>
<body>
    <div class="receipt">
        <div class="receipt-header">
            <h2>HOSPITAL NAME</h2>
            <p>123 Hospital Street, City</p>
            <p>Tel: +233 XX XXX XXXX</p>
            <p>Email: info@hospital.com</p>
            <hr style="border-style: dashed; margin: 10px 0;">
            <h3 style="margin: 5px 0;">OFFICIAL RECEIPT</h3>
            <p style="font-size: 10px;"><?php echo $payment->payment_no; ?></p>
        </div>
        
        <div class="receipt-body">
            <div class="receipt-row">
                <span class="label">Date:</span>
                <span><?php echo date('d M Y H:i', strtotime($payment->collected_at)); ?></span>
            </div>
            <div class="receipt-row">
                <span class="label">Receipt No:</span>
                <span><?php echo $payment->payment_no; ?></span>
            </div>
			<?php if (isset($payment->legacy_receipt_no) && trim((string)$payment->legacy_receipt_no) !== '') { ?>
			<div class="receipt-row">
				<span class="label">Legacy Receipt No:</span>
				<span><?php echo htmlspecialchars((string)$payment->legacy_receipt_no); ?></span>
			</div>
			<?php } ?>
            <div class="receipt-row">
                <span class="label">Bill No:</span>
                <span><?php echo $payment->bill_no; ?></span>
            </div>
			<?php if (isset($payment->legacy_invoice_no) && trim((string)$payment->legacy_invoice_no) !== '') { ?>
			<div class="receipt-row">
				<span class="label">Legacy Invoice No:</span>
				<span><?php echo htmlspecialchars((string)$payment->legacy_invoice_no); ?></span>
			</div>
			<?php } ?>
            <div class="receipt-row">
                <span class="label">Patient ID:</span>
                <span><?php echo $payment->patient_no; ?></span>
            </div>
            <div class="receipt-row">
                <span class="label">Patient Name:</span>
                <span><?php echo $patient->lastname . ' ' . $patient->firstname; ?></span>
            </div>
            
            <hr>
            
            <div class="receipt-row">
                <span class="label">Payment Method:</span>
                <span><?php echo !empty($receipt_payment_method_label) ? htmlspecialchars((string)$receipt_payment_method_label) : $payment->payment_method; ?></span>
            </div>
            <?php if($payment->reference_no): ?>
            <div class="receipt-row">
                <span class="label">Reference:</span>
                <span><?php echo $payment->reference_no; ?></span>
            </div>
            <?php endif; ?>
            
            <div class="amount">
                GHS <?php echo number_format($payment->amount, 2); ?>
            </div>
            
            <div class="receipt-row">
                <span class="label">Amount in Words:</span>
            </div>
            <p style="font-style: italic; text-transform: uppercase;">
                <?php echo convert_number_to_words($payment->amount); ?> Ghana Cedis Only
            </p>
            
            <hr>
			<?php if (isset($receipt_prev_balance) || isset($receipt_payment) || isset($receipt_total_paid) || isset($receipt_amount_tendered) || (isset($payment->legacy_receipt_no) && trim((string)$payment->legacy_receipt_no) !== '')) { ?>
			<div class="receipt-row">
				<span class="label">Previous Balance:</span>
				<span>GHS <?php echo number_format(isset($receipt_prev_balance) ? (float)$receipt_prev_balance : 0, 2); ?></span>
			</div>
			<div class="receipt-row">
				<span class="label">Payment (This Receipt):</span>
				<span>GHS <?php echo number_format(isset($receipt_payment) ? (float)$receipt_payment : (float)$payment->amount, 2); ?></span>
			</div>
			<?php if (isset($receipt_total_paid)) { ?>
			<div class="receipt-row">
				<span class="label">Total Paid (To Date):</span>
				<span>GHS <?php echo number_format((float)$receipt_total_paid, 2); ?></span>
			</div>
			<?php } ?>
			<?php if (isset($receipt_amount_tendered)) { ?>
			<div class="receipt-row">
				<span class="label">Amount Tendered:</span>
				<span>GHS <?php echo number_format((float)$receipt_amount_tendered, 2); ?></span>
			</div>
			<?php } ?>
			<?php if (isset($getOR) && $getOR && isset($getOR->change)) { ?>
			<div class="receipt-row">
				<span class="label">Change:</span>
				<span>GHS <?php echo number_format(max(0, (float)$getOR->change), 2); ?></span>
			</div>
			<?php } ?>
			<hr>
			<?php } ?>
            
            <div class="receipt-row">
                <span class="label">Collected By:</span>
                <span><?php echo !empty($receipt_cashier_name) ? htmlspecialchars((string)$receipt_cashier_name) : $payment->collected_by; ?></span>
            </div>
			<?php if (isset($receipt_outstanding_balance)) { ?>
			<div class="receipt-row">
				<span class="label">Outstanding Balance:</span>
				<span>GHS <?php echo number_format((float)$receipt_outstanding_balance, 2); ?></span>
			</div>
			<?php } ?>
        </div>
        
        <div class="receipt-footer">
            <div class="barcode">
                <?php echo str_repeat('*', 20); ?><br>
                <?php echo $payment->payment_no; ?><br>
                <?php echo str_repeat('*', 20); ?>
            </div>
            <p style="font-size: 10px; margin-top: 10px;">
                Thank you for your payment.<br>
                Please keep this receipt for your records.
            </p>
        </div>
    </div>
    
    <div class="no-print">
        <button onclick="window.print()" class="btn btn-primary">
            <i class="fa fa-print"></i> Print Receipt
        </button>
        <button onclick="window.close()" class="btn btn-default">
            <i class="fa fa-close"></i> Close
        </button>
    </div>

</body>
</html>

<?php
// Helper function to convert number to words
function convert_number_to_words($number) {
    $hyphen = '-';
    $conjunction = ' and ';
    $separator = ', ';
    $negative = 'negative ';
    $decimal = ' point ';
    $dictionary = array(
        0 => 'zero', 1 => 'one', 2 => 'two', 3 => 'three', 4 => 'four',
        5 => 'five', 6 => 'six', 7 => 'seven', 8 => 'eight', 9 => 'nine',
        10 => 'ten', 11 => 'eleven', 12 => 'twelve', 13 => 'thirteen',
        14 => 'fourteen', 15 => 'fifteen', 16 => 'sixteen', 17 => 'seventeen',
        18 => 'eighteen', 19 => 'nineteen', 20 => 'twenty', 30 => 'thirty',
        40 => 'forty', 50 => 'fifty', 60 => 'sixty', 70 => 'seventy',
        80 => 'eighty', 90 => 'ninety', 100 => 'hundred', 1000 => 'thousand'
    );
    
    if (!is_numeric($number)) return false;
    
    $number = (int) $number;
    if ($number < 0) return $negative . convert_number_to_words(abs($number));
    
    $string = $fraction = null;
    
    if (strpos($number, '.') !== false) {
        list($number, $fraction) = explode('.', $number);
    }
    
    switch (true) {
        case $number < 21:
            $string = $dictionary[$number];
            break;
        case $number < 100:
            $tens = ((int) ($number / 10)) * 10;
            $units = $number % 10;
            $string = $dictionary[$tens];
            if ($units) $string .= $hyphen . $dictionary[$units];
            break;
        case $number < 1000:
            $hundreds = $number / 100;
            $remainder = $number % 100;
            $string = $dictionary[$hundreds] . ' ' . $dictionary[100];
            if ($remainder) $string .= $conjunction . convert_number_to_words($remainder);
            break;
        default:
            $baseUnit = pow(1000, floor(log($number, 1000)));
            $numBaseUnits = (int) ($number / $baseUnit);
            $remainder = $number % $baseUnit;
            $string = convert_number_to_words($numBaseUnits) . ' ' . $dictionary[$baseUnit];
            if ($remainder) $string .= $remainder < 100 ? $conjunction : $separator;
            $string .= convert_number_to_words($remainder);
            break;
    }
    
    return $string;
}
?>
