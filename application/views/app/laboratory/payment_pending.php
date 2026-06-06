<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title>Payment Pending - Hebrew Medical Center</title>
        <meta content="width=device-width, initial-scale=1.0" name="viewport">
        <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">

        <link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
        <link href="<?php echo base_url();?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
        <link href="<?php echo base_url();?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
        <link href="<?php echo base_url();?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
        
        <style>
            .payment-pending-card {
                max-width: 800px;
                margin: 50px auto;
                border: none;
                box-shadow: 0 4px 15px rgba(0,0,0,0.1);
                border-radius: 8px;
            }
            .payment-pending-header {
                background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
                color: white;
                padding: 30px;
                border-radius: 8px 8px 0 0;
                text-align: center;
            }
            .payment-pending-header .icon {
                font-size: 64px;
                margin-bottom: 15px;
            }
            .payment-pending-header h2 {
                margin: 0;
                font-size: 28px;
                font-weight: 600;
            }
            .payment-pending-body {
                padding: 40px;
                background: #fff;
                border-radius: 0 0 8px 8px;
            }
            .patient-summary {
                background: #f8f9fa;
                border-left: 4px solid #f39c12;
                padding: 20px;
                margin-bottom: 25px;
                border-radius: 4px;
            }
            .patient-summary h4 {
                margin-top: 0;
                color: #2c3e50;
                font-weight: 600;
            }
            .info-row {
                margin-bottom: 10px;
                display: flex;
            }
            .info-label {
                font-weight: 600;
                color: #666;
                width: 150px;
            }
            .info-value {
                color: #2c3e50;
                flex: 1;
            }
            .test-details {
                background: #fff3cd;
                border: 1px solid #ffc107;
                padding: 20px;
                margin-bottom: 25px;
                border-radius: 4px;
            }
            .test-details h4 {
                margin-top: 0;
                color: #856404;
            }
            .status-badge {
                display: inline-block;
                padding: 8px 16px;
                background: #dc3545;
                color: white;
                border-radius: 20px;
                font-weight: 600;
                font-size: 14px;
            }
            .action-buttons {
                margin-top: 30px;
                text-align: center;
            }
            .action-buttons .btn {
                margin: 5px;
                padding: 12px 30px;
                font-size: 16px;
                border-radius: 4px;
            }
            .btn-billing {
                background: #28a745;
                color: white;
                border: none;
            }
            .btn-billing:hover {
                background: #218838;
                color: white;
            }
            .btn-back {
                background: #6c757d;
                color: white;
                border: none;
            }
            .btn-back:hover {
                background: #5a6268;
                color: white;
            }
            .help-text {
                margin-top: 20px;
                padding: 15px;
                background: #e7f3ff;
                border-left: 4px solid #007bff;
                border-radius: 4px;
                color: #004085;
            }
        </style>
    </head>
    <body class="skin-blue">
        <?php require_once(APPPATH.'views/include/header.php');?>
        
        <div class="wrapper row-offcanvas row-offcanvas-left">
            <?php require_once(APPPATH.'views/include/sidebar.php');?>
            
            <aside class="right-side">
                <section class="content-header">
                    <h1>
                        <i class="fa fa-flask"></i> Laboratory Payment Status
                        <small>Payment verification required</small>
                    </h1>
                    <ol class="breadcrumb">
                        <li><a href="<?php echo base_url();?>app/dashboard"><i class="fa fa-home"></i> Home</a></li>
                        <li><a href="<?php echo base_url();?>app/laboratory">Laboratory</a></li>
                        <li class="active">Payment Pending</li>
                    </ol>
                </section>

                <section class="content">
                    <div class="payment-pending-card">
                        <div class="payment-pending-header">
                            <div class="icon">
                                <i class="fa fa-exclamation-triangle"></i>
                            </div>
                            <h2>Payment Pending</h2>
                            <p style="margin: 10px 0 0 0; font-size: 16px; opacity: 0.9;">
                                This laboratory request requires payment before processing
                            </p>
                        </div>
                        
                        <div class="payment-pending-body">
                            <!-- Patient Summary -->
                            <div class="patient-summary">
                                <h4><i class="fa fa-user"></i> Patient Information</h4>
                                <div class="info-row">
                                    <div class="info-label">Patient Name:</div>
                                    <div class="info-value">
                                        <strong><?php echo isset($patient_name) ? htmlspecialchars($patient_name) : 'N/A'; ?></strong>
                                    </div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Patient No:</div>
                                    <div class="info-value"><?php echo isset($patient_no) ? htmlspecialchars($patient_no) : 'N/A'; ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">OPD Number:</div>
                                    <div class="info-value"><?php echo isset($iop_id) ? htmlspecialchars($iop_id) : 'N/A'; ?></div>
                                </div>
                                <?php if (isset($patient_age) && $patient_age): ?>
                                <div class="info-row">
                                    <div class="info-label">Age:</div>
                                    <div class="info-value"><?php echo htmlspecialchars($patient_age); ?></div>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Test Details -->
                            <div class="test-details">
                                <h4><i class="fa fa-flask"></i> Test Details</h4>
                                <div class="info-row">
                                    <div class="info-label">Test Name:</div>
                                    <div class="info-value">
                                        <strong><?php echo isset($test_name) ? htmlspecialchars($test_name) : 'N/A'; ?></strong>
                                    </div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Requested By:</div>
                                    <div class="info-value">
                                        <?php echo isset($requested_by) ? htmlspecialchars($requested_by) : 'N/A'; ?>
                                    </div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Date Requested:</div>
                                    <div class="info-value">
                                        <?php echo isset($request_date) ? htmlspecialchars($request_date) : 'N/A'; ?>
                                    </div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Payment Status:</div>
                                    <div class="info-value">
                                        <span class="status-badge">
                                            <i class="fa fa-clock-o"></i> 
                                            <?php echo isset($payment_status_label) ? htmlspecialchars($payment_status_label) : 'PENDING'; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Help Text -->
                            <div class="help-text">
                                <i class="fa fa-info-circle"></i>
                                <strong>Important:</strong> This laboratory test cannot be processed until payment is confirmed. 
                                Please complete the billing process or contact the billing department for assistance.
                            </div>
                            
                            <!-- Action Buttons -->
                            <div class="action-buttons">
                                <a href="<?php echo base_url();?>app/pos/pos_visit/<?php echo isset($iop_id) ? url_safe_id($iop_id) : ''; ?>" 
                                   class="btn btn-billing">
                                    <i class="fa fa-credit-card"></i> Go to Billing
                                </a>
                                <a href="<?php echo base_url();?>app/laboratory/request/<?php echo isset($iop_id) ? url_safe_id($iop_id) : ''; ?>" 
                                   class="btn btn-back">
                                    <i class="fa fa-arrow-left"></i> Back to Laboratory Queue
                                </a>
                            </div>
                        </div>
                    </div>
                </section>
            </aside>
        </div>
        
        <script src="<?php echo base_url();?>public/js/jquery.min.js"></script>
        <script src="<?php echo base_url();?>public/js/bootstrap.min.js"></script>
        <script src="<?php echo base_url();?>public/js/AdminLTE/app.js"></script>
    </body>
</html>
