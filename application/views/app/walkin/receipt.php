<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Receipt — <?php echo htmlspecialchars($txn->receipt_number); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo base_url()?>public/css/font-awesome.min.css" rel="stylesheet">
    <link href="<?php echo base_url()?>public/css/AdminLTE.css" rel="stylesheet">
    <style>
        .receipt-wrapper{max-width:560px;margin:0 auto;}
        .receipt-card{background:#fff;border-radius:10px;box-shadow:0 2px 20px rgba(0,0,0,.1);overflow:hidden;}
        .receipt-header{background:linear-gradient(135deg,#1a6fa5,#1e90cc);color:#fff;padding:28px 32px;text-align:center;}
        .receipt-header .hospital-name{font-size:20px;font-weight:700;letter-spacing:-.3px;margin-bottom:4px;}
        .receipt-header .receipt-title{font-size:14px;opacity:.85;text-transform:uppercase;letter-spacing:1px;}
        .receipt-header .receipt-number{font-size:24px;font-weight:700;margin-top:12px;background:rgba(255,255,255,.15);border-radius:6px;padding:6px 16px;display:inline-block;}
        .receipt-body{padding:28px 32px;}
        .receipt-section{margin-bottom:22px;}
        .receipt-section-title{font-size:11px;font-weight:700;text-transform:uppercase;color:#6c757d;letter-spacing:.8px;margin-bottom:10px;padding-bottom:6px;border-bottom:1px solid #f0f0f0;}
        .receipt-row{display:flex;justify-content:space-between;align-items:flex-start;padding:5px 0;font-size:14px;}
        .receipt-row .label{color:#6c757d;flex-shrink:0;width:38%;}
        .receipt-row .value{font-weight:600;text-align:right;word-break:break-word;}
        .receipt-amount-box{background:#f0f9f4;border:2px solid #27a063;border-radius:8px;padding:16px 20px;display:flex;justify-content:space-between;align-items:center;margin:20px 0;}
        .receipt-amount-box .amount-label{font-size:14px;color:#27a063;font-weight:600;}
        .receipt-amount-box .amount-val{font-size:28px;font-weight:800;color:#1a3a2a;}
        .badge-service{display:inline-block;padding:4px 12px;border-radius:12px;font-size:12px;font-weight:600;}
        .svc-Laboratory{background:#dbeafe;color:#1e40af;}
        .svc-Sonography{background:#ede9fe;color:#6d28d9;}
        .svc-Pharmacy{background:#dcfce7;color:#166534;}
        .svc-Procedure{background:#fef3c7;color:#92400e;}
        .svc-Consultation{background:#e0f2fe;color:#0369a1;}
        .svc-Other{background:#f3f4f6;color:#374151;}
        .receipt-footer{background:#f8f9fa;border-top:1px solid #e9ecef;padding:16px 32px;text-align:center;font-size:12px;color:#6c757d;}
        .action-bar{display:flex;gap:10px;margin-top:20px;flex-wrap:wrap;}
        .action-bar .btn{flex:1;height:44px;font-weight:600;border-radius:6px;}
        .status-badge-Paid{display:inline-block;background:#d1fae5;color:#065f46;padding:4px 12px;border-radius:12px;font-weight:700;font-size:13px;}
        .status-badge-Pending{display:inline-block;background:#fef3c7;color:#92400e;padding:4px 12px;border-radius:12px;font-weight:700;font-size:13px;}
        .status-badge-Cancelled{display:inline-block;background:#fee2e2;color:#991b1b;padding:4px 12px;border-radius:12px;font-weight:700;font-size:13px;}
    </style>
</head>
<body class="skin-blue">
<?php require_once(APPPATH.'views/include/header.php'); ?>

<div class="wrapper row-offcanvas row-offcanvas-left">
    <?php require_once(APPPATH.'views/include/sidebar.php'); ?>

    <aside class="right-side">
        <section class="content-header">
            <h1><i class="fa fa-file-text-o"></i> Payment Receipt
                <small>Step 4 of 4 — Complete</small>
            </h1>
            <ol class="breadcrumb">
                <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                <li><a href="<?php echo base_url()?>app/walkin">Walk-In</a></li>
                <li class="active">Receipt</li>
            </ol>
        </section>

        <section class="content">

            <?php
            $hospitalName = (isset($companyInfo) && isset($companyInfo->company_name) && trim((string)$companyInfo->company_name) !== '')
                ? trim((string)$companyInfo->company_name) : 'SBMC Hospital';
            $hospitalAddress = (isset($companyInfo) && isset($companyInfo->address)) ? $companyInfo->address : '';
            $hospitalPhone   = (isset($companyInfo) && isset($companyInfo->phone)) ? $companyInfo->phone : '';
            ?>

            <div class="receipt-wrapper">
                <!-- Success alert -->
                <div class="alert alert-success" style="border-radius:8px;margin-bottom:16px;">
                    <i class="fa fa-check-circle"></i> <strong>Transaction Complete!</strong>
                    Receipt <strong><?php echo htmlspecialchars($txn->receipt_number); ?></strong> has been generated.
                </div>

                <div class="receipt-card">
                    <!-- Header -->
                    <div class="receipt-header">
                        <div class="hospital-name"><?php echo htmlspecialchars($hospitalName); ?></div>
                        <?php if($hospitalAddress): ?>
                        <div style="font-size:12px;opacity:.8;margin-top:2px;"><?php echo htmlspecialchars($hospitalAddress); ?></div>
                        <?php endif; ?>
                        <div class="receipt-title">Walk-In Service Receipt</div>
                        <div class="receipt-number"><?php echo htmlspecialchars($txn->receipt_number); ?></div>
                    </div>

                    <!-- Body -->
                    <div class="receipt-body">

                        <!-- Amount -->
                        <div class="receipt-amount-box">
                            <span class="amount-label"><i class="fa fa-money"></i> Total Amount</span>
                            <span class="amount-val">GHS <?php echo number_format((float)$txn->amount, 2); ?></span>
                        </div>

                        <!-- Status -->
                        <div style="text-align:center;margin-bottom:20px;">
                            <span class="status-badge-<?php echo $txn->payment_status; ?>"><?php echo $txn->payment_status; ?></span>
                        </div>

                        <!-- Client Details -->
                        <div class="receipt-section">
                            <div class="receipt-section-title"><i class="fa fa-user"></i> Client Information</div>
                            <div class="receipt-row">
                                <span class="label">Name</span>
                                <span class="value"><?php echo htmlspecialchars($txn->client_name); ?></span>
                            </div>
                            <?php if($txn->phone): ?>
                            <div class="receipt-row">
                                <span class="label">Phone</span>
                                <span class="value"><?php echo htmlspecialchars($txn->phone); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if($txn->gender): ?>
                            <div class="receipt-row">
                                <span class="label">Gender</span>
                                <span class="value"><?php echo htmlspecialchars($txn->gender); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if($txn->referral): ?>
                            <div class="receipt-row">
                                <span class="label">Referred By</span>
                                <span class="value"><?php echo htmlspecialchars($txn->referral); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Service Details -->
                        <div class="receipt-section">
                            <div class="receipt-section-title"><i class="fa fa-stethoscope"></i> Service Details</div>
                            <div class="receipt-row">
                                <span class="label">Service Type</span>
                                <span class="value">
                                    <span class="badge-service svc-<?php echo $txn->service_type; ?>"><?php echo $txn->service_type; ?></span>
                                </span>
                            </div>
                            <div class="receipt-row">
                                <span class="label">Description</span>
                                <span class="value"><?php echo htmlspecialchars($txn->description); ?></span>
                            </div>
                            <div class="receipt-row">
                                <span class="label">Amount</span>
                                <span class="value" style="font-size:16px;">GHS <?php echo number_format((float)$txn->amount, 2); ?></span>
                            </div>

                            <?php if ($txn->service_type === 'Pharmacy' && isset($txn->items) && is_array($txn->items) && count($txn->items) > 0): ?>
                                <div style="margin-top:12px;">
                                    <div class="receipt-section-title"><i class="fa fa-medkit"></i> Items</div>
                                    <div class="table-responsive">
                                        <table class="table table-condensed" style="margin:0;">
                                            <thead>
                                            <tr style="background:#f8f9fa;">
                                                <th>Drug</th>
                                                <th style="width:70px; text-align:right;">Qty</th>
                                                <th style="width:110px; text-align:right;">Unit</th>
                                                <th style="width:120px; text-align:right;">Line</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            <?php foreach ($txn->items as $it): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($it->drug_name ?: ('Drug #' . (int)$it->drug_id)); ?></td>
                                                    <td style="text-align:right;"><?php echo number_format((float)$it->qty, 2); ?></td>
                                                    <td style="text-align:right;">GHS <?php echo number_format((float)$it->unit_price, 2); ?></td>
                                                    <td style="text-align:right;">GHS <?php echo number_format((float)$it->line_total, 2); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            <?php endif; ?>

							<?php if ($txn->service_type !== 'Pharmacy' && isset($txn->service_items) && is_array($txn->service_items) && count($txn->service_items) > 0): ?>
								<div style="margin-top:12px;">
									<div class="receipt-section-title"><i class="fa fa-list"></i> Items</div>
									<div class="table-responsive">
										<table class="table table-condensed" style="margin:0;">
											<thead>
											<tr style="background:#f8f9fa;">
												<th>Item</th>
												<th style="width:70px; text-align:right;">Qty</th>
												<th style="width:110px; text-align:right;">Unit</th>
												<th style="width:120px; text-align:right;">Line</th>
											</tr>
											</thead>
											<tbody>
											<?php foreach ($txn->service_items as $it): ?>
												<tr>
													<td><?php echo htmlspecialchars($it->item_name); ?></td>
													<td style="text-align:right;"><?php echo number_format((float)$it->qty, 2); ?></td>
													<td style="text-align:right;">GHS <?php echo number_format((float)$it->unit_price, 2); ?></td>
													<td style="text-align:right;">GHS <?php echo number_format((float)$it->line_total, 2); ?></td>
												</tr>
											<?php endforeach; ?>
											</tbody>
										</table>
									</div>
								</div>
							<?php endif; ?>
                        </div>

                        <!-- Payment Info -->
                        <div class="receipt-section">
                            <div class="receipt-section-title"><i class="fa fa-credit-card"></i> Payment Information</div>
                            <div class="receipt-row">
                                <span class="label">Method</span>
                                <span class="value"><?php echo htmlspecialchars($txn->payment_method); ?></span>
                            </div>
                            <div class="receipt-row">
                                <span class="label">Date &amp; Time</span>
                                <span class="value"><?php echo date('D, d M Y H:i', strtotime($txn->transaction_date)); ?></span>
                            </div>
                            <div class="receipt-row">
                                <span class="label">Cashier</span>
                                <span class="value"><?php echo htmlspecialchars($txn->cashier_name ?: $txn->cashier_id); ?></span>
                            </div>
                            <?php if($txn->notes): ?>
                            <div class="receipt-row">
                                <span class="label">Notes</span>
                                <span class="value"><?php echo htmlspecialchars($txn->notes); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>

                    </div>

                    <!-- Footer -->
                    <div class="receipt-footer">
                        <i class="fa fa-check-circle text-success"></i> Thank you for visiting <?php echo htmlspecialchars($hospitalName); ?>.
                        <?php if($hospitalPhone): ?>
                        &nbsp;|&nbsp; <i class="fa fa-phone"></i> <?php echo htmlspecialchars($hospitalPhone); ?>
                        <?php endif; ?>
                        <br>This receipt is computer-generated and valid without a signature.
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="action-bar">
                    <?php if ($txn->payment_status === 'Pending'): ?>
                        <a href="<?php echo base_url()?>app/walkin/mark_paid/<?php echo $txn->id; ?>" class="btn btn-success" onclick="return confirm('Mark this transaction as PAID? Stock will be deducted for Pharmacy transactions.');">
                            <i class="fa fa-check"></i> Mark as Paid
                        </a>
                    <?php endif; ?>
                    <a href="<?php echo base_url()?>app/walkin/print_receipt/<?php echo $txn->id; ?>" class="btn btn-primary" target="_blank">
                        <i class="fa fa-print"></i> Print Receipt
                    </a>
                    <a href="<?php echo base_url()?>app/walkin/add_transaction/<?php echo $txn->walkin_client_id; ?>" class="btn btn-success">
                        <i class="fa fa-plus"></i> Add Another Service
                    </a>
                    <a href="<?php echo base_url()?>app/walkin/register" class="btn btn-info">
                        <i class="fa fa-user-plus"></i> New Walk-In
                    </a>
                    <a href="<?php echo base_url()?>app/walkin" class="btn btn-default">
                        <i class="fa fa-dashboard"></i> Dashboard
                    </a>
                </div>
            </div>

        </section>
    </aside>
</div>

<script src="<?php echo base_url()?>public/js/jquery.min.js"></script>
<script src="<?php echo base_url()?>public/js/bootstrap.min.js"></script>
<script src="<?php echo base_url()?>public/js/AdminLTE/app.js"></script>
</body>
</html>
