<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Invoice <?php echo $bill->bill_no; ?></title>
    <link href="<?php echo base_url(); ?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css">
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; margin: 20px; }
        .invoice { max-width: 800px; margin: 0 auto; }
        .invoice-header { border-bottom: 3px solid #333; padding-bottom: 20px; margin-bottom: 30px; }
        .invoice-header h1 { margin: 0; font-size: 28px; }
        .invoice-header h2 { margin: 5px 0; font-size: 16px; color: #666; }
        .invoice-title { text-align: center; margin: 30px 0; }
        .invoice-title h2 { font-size: 24px; text-transform: uppercase; border: 2px solid #333; display: inline-block; padding: 10px 40px; }
        .info-section { margin-bottom: 20px; }
        .info-section h4 { margin-bottom: 10px; border-bottom: 1px solid #ddd; padding-bottom: 5px; }
        .table-invoice th, .table-invoice td { padding: 10px; border: 1px solid #ddd; }
        .table-invoice th { background: #f5f5f5; }
        .total-section { margin-top: 20px; text-align: right; }
        .total-section table { margin-left: auto; }
        .total-section td { padding: 5px 15px; }
        .grand-total { font-size: 18px; font-weight: bold; background: #f5f5f5; }
        .signature-section { margin-top: 60px; }
        .signature-line { border-top: 1px solid #333; width: 250px; margin-top: 40px; padding-top: 5px; }
        .no-print { margin-top: 30px; text-align: center; }
        .status-paid { color: #00a65a; font-weight: bold; }
        .status-pending { color: #f39c12; font-weight: bold; }
        @media print {
            .no-print { display: none; }
            body { margin: 0; }
        }
    </style>
</head>
<body>
    <div class="invoice">
        <!-- Header -->
        <div class="invoice-header">
            <div class="row">
                <div class="col-md-6">
                    <h1>HOSPITAL NAME</h1>
                    <h2>Hospital Management System</h2>
                    <p>
                        123 Hospital Street<br>
                        City, Country<br>
                        Tel: +233 XX XXX XXXX<br>
                        Email: info@hospital.com
                    </p>
                </div>
                <div class="col-md-6 text-right">
                    <p><strong>Bill No:</strong> <?php echo $bill->bill_no; ?></p>
                    <p><strong>Date:</strong> <?php echo date('d M Y', strtotime($bill->created_at)); ?></p>
                    <p><strong>Status:</strong> 
                        <span class="status-<?php echo strtolower($bill->payment_status); ?>">
                            <?php echo $bill->payment_status; ?>
                        </span>
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Invoice Title -->
        <div class="invoice-title">
            <h2>PATIENT BILL / INVOICE</h2>
        </div>
        
        <!-- Patient & Visit Info -->
        <div class="row">
            <div class="col-md-6">
                <div class="info-section">
                    <h4>Bill To:</h4>
                    <p>
                        <strong><?php echo $patient->lastname . ' ' . $patient->firstname; ?></strong><br>
                        Patient No: <?php echo $bill->patient_no; ?><br>
                        Payer Type: <?php echo $bill->payer_type; ?>
                        <?php if($bill->insurance_id): ?><br>Insurance ID: <?php echo $bill->insurance_id; ?><?php endif; ?>
                    </p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="info-section">
                    <h4>Visit Information:</h4>
                    <p>
                        Visit Type: <?php echo $bill->visit_type; ?><br>
                        Visit ID: <?php echo $bill->visit_id; ?><br>
                        Created: <?php echo date('d M Y H:i', strtotime($bill->created_at)); ?><br>
                        Created By: <?php echo $bill->created_by; ?>
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Bill Items -->
        <div class="info-section">
            <h4>Bill Details:</h4>
            <table class="table table-invoice" width="100%">
                <thead>
                    <tr>
                        <th style="width: 40%;">Description</th>
                        <th style="width: 15%;">Department</th>
                        <th style="width: 10%;">Qty</th>
                        <th style="width: 15%;">Unit Price</th>
                        <th style="width: 20%;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($items as $item): ?>
                    <tr>
                        <td>
                            <?php echo $item->service_name; ?><br>
                            <small class="text-muted"><?php echo $item->service_type; ?></small>
                        </td>
                        <td><?php echo $item->department; ?></td>
                        <td><?php echo number_format($item->quantity, 2); ?></td>
                        <td>GHS <?php echo number_format($item->unit_price, 2); ?></td>
                        <td>
                            <?php if($item->discount_amount > 0): ?>
                            <del class="text-muted">GHS <?php echo number_format($item->gross_amount, 2); ?></del><br>
                            <?php endif; ?>
                            GHS <?php echo number_format($item->net_amount, 2); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Totals -->
        <div class="total-section">
            <table>
                <tr>
                    <td><strong>Subtotal:</strong></td>
                    <td>GHS <?php echo number_format($bill->total_amount, 2); ?></td>
                </tr>
                <?php if($bill->discount_amount > 0): ?>
                <tr>
                    <td><strong>Discount:</strong></td>
                    <td style="color: #00a65a;">-GHS <?php echo number_format($bill->discount_amount, 2); ?></td>
                </tr>
                <?php endif; ?>
                <?php if($bill->tax_amount > 0): ?>
                <tr>
                    <td><strong>Tax:</strong></td>
                    <td>GHS <?php echo number_format($bill->tax_amount, 2); ?></td>
                </tr>
                <?php endif; ?>
                <tr class="grand-total">
                    <td><strong>Net Amount:</strong></td>
                    <td><strong>GHS <?php echo number_format($bill->net_amount, 2); ?></strong></td>
                </tr>
                <tr>
                    <td><strong>Amount Paid:</strong></td>
                    <td>GHS <?php echo number_format($bill->paid_amount, 2); ?></td>
                </tr>
                <tr style="color: <?php echo $bill->balance_due > 0 ? '#f56954' : '#00a65a'; ?>;">
                    <td><strong>Balance Due:</strong></td>
                    <td><strong>GHS <?php echo number_format($bill->balance_due, 2); ?></strong></td>
                </tr>
            </table>
        </div>
        
        <!-- Payment History -->
        <?php if(!empty($payments)): ?>
        <div class="info-section" style="margin-top: 30px;">
            <h4>Payment History:</h4>
            <table class="table table-bordered" width="100%">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Method</th>
                        <th>Reference</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($payments as $payment): ?>
                    <tr>
                        <td><?php echo date('d M Y H:i', strtotime($payment->collected_at)); ?></td>
                        <td><?php echo $payment->payment_method; ?></td>
                        <td><?php echo $payment->reference_no ?: '-'; ?></td>
                        <td>GHS <?php echo number_format($payment->amount, 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <!-- Signatures -->
        <div class="signature-section">
            <div class="row">
                <div class="col-md-6">
                    <div class="signature-line">
                        <strong>Billed By:</strong><br>
                        <?php echo $bill->created_by; ?>
                    </div>
                </div>
                <div class="col-md-6 text-right">
                    <div class="signature-line" style="margin-left: auto;">
                        <strong>Authorized By:</strong><br>
                        <?php echo $bill->billed_by ?: '____________________'; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd; text-align: center; font-size: 10px; color: #666;">
            <p>This is a computer-generated document. No signature required unless specified.</p>
            <p>For inquiries, please contact the billing department.</p>
            <p><?php echo date('d M Y H:i:s'); ?> | HMS Unified Billing System v3.0</p>
        </div>
    </div>
    
    <div class="no-print">
        <button onclick="window.print()" class="btn btn-primary">
            <i class="fa fa-print"></i> Print Invoice
        </button>
        <button onclick="window.close()" class="btn btn-default">
            <i class="fa fa-close"></i> Close
        </button>
    </div>

</body>
</html>
