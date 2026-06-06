<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Hebrew Medical Center — Claim Detail</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/hms-enhanced.css" rel="stylesheet" type="text/css" />
</head>
<body class="skin-blue">
    <?php require_once(APPPATH.'views/include/header.php');?>
    <div class="wrapper row-offcanvas row-offcanvas-left">
        <?php require_once(APPPATH.'views/include/sidebar.php');?>
        <aside class="right-side">
            <section class="content-header">
                <h1><i class="fa fa-medkit"></i> Claim: <?php echo htmlspecialchars($claim->claim_ref); ?></h1>
                <ol class="breadcrumb">
                    <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Dashboard</a></li>
                    <li><a href="<?php echo base_url()?>app/nhis_claims">NHIS Claims</a></li>
                    <li class="active"><?php echo htmlspecialchars($claim->claim_ref); ?></li>
                </ol>
            </section>

            <section class="content">
                <?php if(isset($message) && $message){ echo $message; } ?>

                <?php
                    $statusClass = 'label-default';
                    if($claim->status==='APPROVED') $statusClass = 'label-success';
                    elseif($claim->status==='REJECTED') $statusClass = 'label-danger';
                    elseif($claim->status==='SUBMITTED') $statusClass = 'label-info';
                    elseif($claim->status==='PENDING') $statusClass = 'label-warning';

                    $reconClass = 'label-default';
                    $reconLabel = isset($claim->recon_status) && $claim->recon_status ? $claim->recon_status : 'Not Reconciled';
                    if(isset($claim->recon_status)){
                        if($claim->recon_status==='MATCHED') $reconClass = 'label-success';
                        elseif($claim->recon_status==='UNDERPAID') $reconClass = 'label-warning';
                        elseif($claim->recon_status==='REJECTED') $reconClass = 'label-danger';
                        elseif($claim->recon_status==='OVERPAID') $reconClass = 'label-info';
                    }

                    $approvedAmt = isset($claim->approved_amount) && $claim->approved_amount !== null ? (float)$claim->approved_amount : null;
                    $patName = (isset($patient) && $patient) ? trim((isset($patient->firstname)?$patient->firstname:'').' '.(isset($patient->lastname)?$patient->lastname:'')) : $claim->patient_no;
                ?>

                <!-- Claim Header -->
                <div class="row">
                    <div class="col-md-8">
                        <div class="box box-primary">
                            <div class="box-header with-border">
                                <h3 class="box-title"><i class="fa fa-info-circle"></i> Claim Information</h3>
                                <div class="box-tools pull-right">
                                    <span class="label <?php echo $statusClass; ?>" style="font-size:14px;"><?php echo $claim->status; ?></span>
                                </div>
                            </div>
                            <div class="box-body">
                                <table class="table table-bordered">
                                    <tr><th width="200">Claim Reference</th><td><strong><?php echo htmlspecialchars($claim->claim_ref); ?></strong></td></tr>
                                    <tr><th>Patient</th><td><a href="<?php echo base_url(); ?>app/patient/view/<?php echo $claim->patient_no; ?>"><?php echo htmlspecialchars($patName); ?></a> (<?php echo htmlspecialchars($claim->patient_no); ?>)</td></tr>
                                    <tr><th>NHIS Number</th><td><?php echo htmlspecialchars($claim->nhis_number); ?></td></tr>
                                    <tr><th>Visit (IOP ID)</th><td><?php echo htmlspecialchars($claim->iop_id); ?></td></tr>
                                    <tr><th>Invoice No</th><td><?php echo $claim->invoice_no ? htmlspecialchars($claim->invoice_no) : '—'; ?></td></tr>
                                    <tr><th>API Mode</th><td><span class="label label-<?php echo (isset($claim->api_mode) && $claim->api_mode==='LIVE')?'success':'info'; ?>"><?php echo isset($claim->api_mode) ? $claim->api_mode : 'N/A'; ?></span></td></tr>
                                    <tr><th>API Reference</th><td><?php echo isset($claim->api_ref) && $claim->api_ref ? htmlspecialchars($claim->api_ref) : '—'; ?></td></tr>
                                    <tr><th>Created</th><td><?php echo date('M d, Y H:i', strtotime($claim->created_at)); ?></td></tr>
                                    <?php if($claim->submitted_at): ?><tr><th>Submitted</th><td><?php echo date('M d, Y H:i', strtotime($claim->submitted_at)); ?></td></tr><?php endif; ?>
                                    <?php if($claim->approved_at): ?><tr><th>Approved</th><td><?php echo date('M d, Y H:i', strtotime($claim->approved_at)); ?></td></tr><?php endif; ?>
                                    <?php if($claim->rejected_at): ?><tr><th>Rejected</th><td><?php echo date('M d, Y H:i', strtotime($claim->rejected_at)); ?></td></tr><?php endif; ?>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <!-- Financial Summary -->
                        <div class="box box-success">
                            <div class="box-header with-border">
                                <h3 class="box-title"><i class="fa fa-money"></i> Financial Summary</h3>
                            </div>
                            <div class="box-body">
                                <table class="table">
                                    <tr><th>Total Amount</th><td class="text-right"><strong>GHS <?php echo number_format((float)$claim->total_amount, 2); ?></strong></td></tr>
                                    <tr><th>NHIS Claimed</th><td class="text-right text-blue"><strong>GHS <?php echo number_format((float)$claim->nhis_amount, 2); ?></strong></td></tr>
                                    <tr><th>Patient Portion</th><td class="text-right">GHS <?php echo number_format((float)$claim->patient_amount, 2); ?></td></tr>
                                    <tr class="<?php echo ($approvedAmt !== null && $approvedAmt < (float)$claim->nhis_amount) ? 'danger' : ''; ?>">
                                        <th>NHIS Approved</th>
                                        <td class="text-right"><strong><?php echo $approvedAmt !== null ? 'GHS '.number_format($approvedAmt, 2) : '—'; ?></strong></td>
                                    </tr>
                                    <?php if($approvedAmt !== null && $approvedAmt < (float)$claim->nhis_amount): ?>
                                    <tr class="warning">
                                        <th>Shortfall</th>
                                        <td class="text-right text-red"><strong>GHS <?php echo number_format((float)$claim->nhis_amount - $approvedAmt, 2); ?></strong></td>
                                    </tr>
                                    <?php endif; ?>
                                </table>
                            </div>
                        </div>

                        <!-- Reconciliation -->
                        <div class="box box-warning">
                            <div class="box-header with-border">
                                <h3 class="box-title"><i class="fa fa-balance-scale"></i> Reconciliation</h3>
                            </div>
                            <div class="box-body">
                                <p><strong>Status:</strong> <span class="label <?php echo $reconClass; ?>"><?php echo $reconLabel; ?></span></p>
                                <?php if(isset($claim->recon_notes) && $claim->recon_notes): ?>
                                <p><small><?php echo htmlspecialchars($claim->recon_notes); ?></small></p>
                                <?php endif; ?>
                                <?php if(isset($claim->recon_at) && $claim->recon_at): ?>
                                <p><small class="text-muted">Reconciled: <?php echo date('M d, Y H:i', strtotime($claim->recon_at)); ?></small></p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Rejection Info -->
                        <?php if($claim->status === 'REJECTED' && isset($claim->rejection_reason) && $claim->rejection_reason): ?>
                        <div class="box box-danger">
                            <div class="box-header with-border">
                                <h3 class="box-title"><i class="fa fa-ban"></i> Rejection Reason</h3>
                            </div>
                            <div class="box-body">
                                <p><?php echo htmlspecialchars($claim->rejection_reason); ?></p>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Actions -->
                        <div class="box box-default">
                            <div class="box-header with-border">
                                <h3 class="box-title"><i class="fa fa-cogs"></i> Actions</h3>
                            </div>
                            <div class="box-body">
                                <?php if($claim->status === 'PENDING'): ?>
                                <a href="<?php echo base_url(); ?>app/nhis_claims/submit_claim/<?php echo $claim->claim_id; ?>" class="btn btn-info btn-block" onclick="return confirm('Submit this claim to NHIS?');">
                                    <i class="fa fa-upload"></i> Submit to NHIS
                                </a>
                                <?php endif; ?>
                                <?php if(in_array($claim->status, array('APPROVED','REJECTED'))): ?>
                                <a href="<?php echo base_url(); ?>app/nhis_claims/reconcile/<?php echo $claim->claim_id; ?>" class="btn btn-warning btn-block">
                                    <i class="fa fa-refresh"></i> Re-Reconcile
                                </a>
                                <?php endif; ?>
                                <a href="<?php echo base_url(); ?>app/nhis_claims" class="btn btn-default btn-block" style="margin-top:5px;">
                                    <i class="fa fa-arrow-left"></i> Back to Dashboard
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Claim Lines -->
                <div class="box box-info">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-list-alt"></i> Claim Line Items</h3>
                    </div>
                    <div class="box-body table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Service Type</th>
                                    <th>Service Name</th>
                                    <th class="text-right">Qty</th>
                                    <th class="text-right">Unit Price</th>
                                    <th class="text-right">Total</th>
                                    <th class="text-right">NHIS Covered</th>
                                    <th class="text-right">Patient Pays</th>
                                    <th>NHIS?</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if(isset($claim_lines) && $claim_lines): $ln=1; foreach($claim_lines as $line): ?>
                                <tr>
                                    <td><?php echo $ln++; ?></td>
                                    <td><?php echo htmlspecialchars($line->service_type); ?></td>
                                    <td><?php echo htmlspecialchars($line->service_name); ?></td>
                                    <td class="text-right"><?php echo number_format((float)$line->quantity, 0); ?></td>
                                    <td class="text-right"><?php echo number_format((float)$line->unit_price, 2); ?></td>
                                    <td class="text-right"><?php echo number_format((float)$line->total_price, 2); ?></td>
                                    <td class="text-right text-green"><?php echo number_format((float)$line->nhis_covered, 2); ?></td>
                                    <td class="text-right text-red"><?php echo number_format((float)$line->patient_pays, 2); ?></td>
                                    <td><?php echo $line->is_nhis_covered ? '<span class="label label-success">Yes</span>' : '<span class="label label-default">No</span>'; ?></td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="9" class="text-center text-muted">No line items found.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- API Response (collapsible) -->
                <?php if(isset($claim->api_response) && $claim->api_response): ?>
                <div class="box box-default collapsed-box">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-code"></i> Raw API Response</h3>
                        <div class="box-tools pull-right">
                            <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-plus"></i></button>
                        </div>
                    </div>
                    <div class="box-body">
                        <pre style="max-height:200px; overflow:auto; background:#f5f5f5; padding:10px; border-radius:4px;"><?php echo htmlspecialchars(json_encode(json_decode($claim->api_response), JSON_PRETTY_PRINT)); ?></pre>
                    </div>
                </div>
                <?php endif; ?>

            </section>
        </aside>
    </div>

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/2.1.1/jquery.min.js"></script>
    <script src="<?php echo base_url()?>public/js/bootstrap.min.js" type="text/javascript"></script>
    <script src="<?php echo base_url()?>public/js/AdminLTE/app.js" type="text/javascript"></script>
</body>
</html>
