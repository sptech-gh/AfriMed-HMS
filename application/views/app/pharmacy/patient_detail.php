<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Patient Prescriptions - Pharmacy</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/hms-enhanced.css?v=<?php echo time(); ?>" rel="stylesheet" type="text/css" />
    <style>
        .patient-header { background: linear-gradient(135deg, #3c8dbc 0%, #2c6d9c 100%); color: #fff; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        .patient-header h2 { margin: 0 0 10px 0; font-weight: 700; font-size: 24px; }
        .patient-header .patient-meta { font-size: 14px; color: rgba(255,255,255,0.95); }
        .rx-item { border: 1px solid var(--hms-border, #e0e0e0); border-radius: 8px; padding: 18px; margin-bottom: 15px; background: var(--hms-surface, #ffffff) !important; box-shadow: 0 2px 6px rgba(0,0,0,0.06); color: var(--hms-text, #212529) !important; }
        .rx-item.status-dispensed { border-left: 5px solid #28a745; background: var(--hms-success-bg, #f8fff9) !important; }
        .rx-item.status-partial { border-left: 5px solid #ffc107; background: var(--hms-warning-bg, #fffdf5) !important; }
        .rx-item.status-pending { border-left: 5px solid #dc3545; background: var(--hms-surface, #ffffff) !important; }
        .rx-item.status-external { border-left: 5px solid #17a2b8; background: var(--hms-info-bg, #f5fcff) !important; }
        .rx-item.status-unavailable { border-left: 5px solid #6c757d; background: var(--hms-surface-2, #fafafa) !important; }
        .rx-item * { color: inherit; }
        .rx-item .rx-drug-name { color: var(--hms-text, #1a1a1a) !important; }
        .rx-item .rx-details { color: var(--hms-text-muted, #495057) !important; }
        .rx-item .rx-details span { color: var(--hms-text-muted, #495057) !important; }
        .rx-item .rx-details i { color: var(--hms-text-muted, #6c757d) !important; }
        .rx-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; }
        .rx-drug-name { font-size: 18px; font-weight: 700; color: var(--hms-text, #1a1a1a); }
        .rx-details { font-size: 14px; color: var(--hms-text-muted, #495057); font-weight: 500; }
        .rx-details i { color: var(--hms-text-muted, #6c757d); }
        .rx-actions { margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--hms-border, #e9ecef); }
        .rx-progress { display: flex; align-items: center; gap: 12px; margin-top: 10px; }
        .rx-progress .progress { flex: 1; margin-bottom: 0; height: 24px; border-radius: 6px; background: var(--hms-surface-2, #e9ecef); }
        .rx-progress .progress-bar { font-size: 13px; font-weight: 600; line-height: 24px; }
        .stock-warning { color: var(--hms-danger, #dc3545); font-weight: 700; }
        
        /* Improved label/badge visibility */
        .label { font-size: 12px; font-weight: 600; padding: 6px 12px; border-radius: 4px; display: inline-block; text-transform: uppercase; letter-spacing: 0.3px; }
        .label-success { background-color: var(--hms-success, #28a745); color: #fff !important; }
        .label-warning { background-color: var(--hms-warning, #ffc107); color: #212529 !important; }
        .label-danger { background-color: var(--hms-danger, #dc3545); color: #fff !important; }
        .label-info { background-color: var(--hms-info, #17a2b8); color: #fff !important; }
        .label-default { background-color: var(--hms-border-strong, #6c757d); color: var(--hms-text, #fff) !important; }
        .label-primary { background-color: var(--hms-primary, #007bff); color: #fff !important; }
        
        .payment-badge { font-size: 11px; font-weight: 600; padding: 4px 8px; }
        .action-btn-group { display: flex; flex-wrap: wrap; gap: 8px; }
        .quick-actions { background: var(--hms-surface, #f8f9fa); padding: 20px; border-radius: 8px; margin-bottom: 20px; border: 1px solid var(--hms-border, #e9ecef); }
        .quick-actions h4 { color: #212529; font-weight: 600; margin-bottom: 15px; }
        
        /* Button improvements */
        .btn { font-weight: 600; border-radius: 6px; }
        .btn-success { background-color: #28a745; border-color: #28a745; }
        .btn-primary { background-color: #007bff; border-color: #007bff; }
        .btn-warning { background-color: #ffc107; border-color: #ffc107; color: #212529; }
        .btn-danger { background-color: #dc3545; border-color: #dc3545; }
        .btn-info { background-color: #17a2b8; border-color: #17a2b8; }
        .btn-default { background-color: #f8f9fa; border-color: #dee2e6; color: #495057; }
        
        /* Alert improvements */
        .alert { border-radius: 6px; font-weight: 500; }
        .alert-danger { background-color: #f8d7da; border-color: #f5c6cb; color: #721c24; }
        .alert-success { background-color: #d4edda; border-color: #c3e6cb; color: #155724; }
        .alert-warning { background-color: #fff3cd; border-color: #ffeeba; color: #856404; }
        .alert-info { background-color: #d1ecf1; border-color: #bee5eb; color: #0c5460; }
        
        /* Box styling */
        .box { border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); border: none; }
        .box-header { border-bottom: 1px solid #e9ecef; padding: 15px 20px; }
        .box-title { font-weight: 600; color: #212529; font-size: 16px; }
        .box-body { padding: 20px; }
        
        /* Progress bar colors */
        .progress-bar-success { background-color: #28a745; }
        .progress-bar-warning { background-color: #ffc107; color: #212529; }
        .progress-bar-danger { background-color: #dc3545; }

        /* ===== PHASE 6B additions ===== */
        .priority-badge {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 6px 14px; border-radius: 20px;
            font-size: 13px; font-weight: 700; letter-spacing: .4px;
        }
        .priority-badge.urgent  { background: #f8d7da; color: #7b0000; border: 1px solid #f5c6cb; }
        .priority-badge.stat    { background: #fff3cd; color: #6b4c00; border: 1px solid #ffeeba; }
        .priority-badge.prn     { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .rx-meta-strip {
            background: #f8f9fa; border-radius: 6px; padding: 8px 12px;
            margin-bottom: 10px; font-size: 12px; color: #555;
            display: flex; flex-wrap: wrap; gap: 12px; align-items: center;
        }
        .rx-meta-strip span { display: inline-flex; align-items: center; gap: 4px; }
        /* Toast */
        .pd-toast-container { position: fixed; top: 70px; right: 20px; z-index: 9999; min-width: 280px; }
        .pd-toast { padding: 14px 20px; border-radius: 8px; margin-bottom: 8px;
            font-size: 14px; font-weight: 500; box-shadow: 0 4px 12px rgba(0,0,0,.15);
            animation: pdSlide .3s ease; color: #fff; }
        .pd-toast.success { background: #28a745; }
        .pd-toast.error   { background: #dc3545; }
        .pd-toast.warning { background: #856404; }
        @keyframes pdSlide { from { transform: translateX(120%); opacity:0; } to { transform: translateX(0); opacity:1; } }
        /* Inline status update flash */
        .rx-item.just-dispensed { animation: flashGreen 1s ease; }
        @keyframes flashGreen { 0%,100% { background: inherit; } 50% { background: #d4edda; } }
        /* AJAX submit spinner */
        .btn-ajax-spin .fa { display: none; }
        .btn-ajax-spin .fa-spin-inline { display: inline-block !important; }

        .theme-dark {
            --primary: var(--hms-primary);
            --success: var(--hms-success);
            --warning: var(--hms-warning);
            --danger:  var(--hms-danger);
            --info:    var(--hms-info);
            --purple:  #7c3aed;
            --shadow:  0 2px 10px rgba(0,0,0,0.4);
        }

        /* Dark mode overrides */
        .theme-dark .quick-actions {
            background: var(--hms-surface, #0f172a) !important;
            border-color: var(--hms-border, rgba(255,255,255,0.1)) !important;
            color: var(--hms-text, rgba(255,255,255,0.88)) !important;
        }
        .theme-dark .quick-actions h4 {
            color: var(--hms-text, rgba(255,255,255,0.88)) !important;
        }
        .theme-dark .quick-actions p.text-muted {
            color: var(--hms-text-muted, rgba(255,255,255,0.60)) !important;
        }
        .theme-dark .rx-item {
            background: var(--hms-surface, #0f172a) !important;
            border-color: var(--hms-border, rgba(255,255,255,0.1)) !important;
            color: var(--hms-text, rgba(255,255,255,0.88)) !important;
            box-shadow: var(--shadow) !important;
        }
        .theme-dark .rx-item.status-dispensed {
            border-left-color: #28a745 !important;
            background: rgba(40, 167, 69, 0.08) !important;
        }
        .theme-dark .rx-item.status-partial {
            border-left-color: #ffc107 !important;
            background: rgba(255, 193, 7, 0.08) !important;
        }
        .theme-dark .rx-item.status-pending {
            border-left-color: #dc3545 !important;
            background: rgba(220, 53, 69, 0.08) !important;
        }
        .theme-dark .rx-item.status-external {
            border-left-color: #17a2b8 !important;
            background: rgba(23, 162, 184, 0.08) !important;
        }
        .theme-dark .rx-item.status-unavailable {
            border-left-color: #6c757d !important;
            background: var(--hms-surface-2, rgba(255,255,255,0.02)) !important;
        }
        .theme-dark .rx-item .rx-drug-name {
            color: var(--hms-text, rgba(255,255,255,0.88)) !important;
        }
        .theme-dark .rx-item .rx-details,
        .theme-dark .rx-item .rx-details span {
            color: var(--hms-text-muted, rgba(255,255,255,0.60)) !important;
        }
        .theme-dark .rx-item .rx-details i {
            color: var(--hms-text-muted, rgba(255,255,255,0.60)) !important;
        }
        .theme-dark .rx-meta-strip {
            background: var(--hms-surface-2, rgba(255,255,255,0.02)) !important;
            color: var(--hms-text-muted, rgba(255,255,255,0.60)) !important;
            border: 1px solid var(--hms-border, rgba(255,255,255,0.1)) !important;
        }
        .theme-dark .rx-meta-strip strong {
            color: var(--hms-text, rgba(255,255,255,0.88)) !important;
        }
        .theme-dark .rx-progress .progress {
            background: var(--hms-surface-2, rgba(255,255,255,0.02)) !important;
        }
        .theme-dark .rx-actions {
            border-top-color: var(--hms-border, rgba(255,255,255,0.1)) !important;
        }
        .theme-dark .priority-badge.urgent {
            background: rgba(220, 53, 69, 0.15) !important;
            color: #ff8b94 !important;
            border-color: rgba(220, 53, 69, 0.3) !important;
        }
        .theme-dark .priority-badge.stat {
            background: rgba(255, 193, 7, 0.15) !important;
            color: #ffe082 !important;
            border-color: rgba(255, 193, 7, 0.3) !important;
        }
        .theme-dark .priority-badge.prn {
            background: rgba(23, 162, 184, 0.15) !important;
            color: #80deea !important;
            border-color: rgba(23, 162, 184, 0.3) !important;
        }
        .theme-dark .box {
            background: var(--hms-surface, #0f172a) !important;
            border-color: var(--hms-border, rgba(255,255,255,0.1)) !important;
            color: var(--hms-text, rgba(255,255,255,0.88)) !important;
        }
        .theme-dark .box-header {
            border-bottom-color: var(--hms-border, rgba(255,255,255,0.1)) !important;
        }
        .theme-dark .box-title {
            color: var(--hms-text, rgba(255,255,255,0.88)) !important;
        }
        .theme-dark .form-control {
            background-color: var(--hms-input-bg, rgba(255,255,255,0.05)) !important;
            color: var(--hms-input-text, rgba(255,255,255,0.90)) !important;
            border-color: var(--hms-input-border, rgba(255,255,255,0.14)) !important;
        }
        .theme-dark .form-control[readonly] {
            background-color: var(--hms-surface-2, rgba(255,255,255,0.02)) !important;
            color: var(--hms-text-muted, rgba(255,255,255,0.60)) !important;
        }
        .theme-dark .modal-content {
            background: var(--hms-surface, #0f172a) !important;
            color: var(--hms-text, rgba(255,255,255,0.88)) !important;
            border: 1px solid var(--hms-border, rgba(255,255,255,0.1)) !important;
        }
        .theme-dark .modal-header {
            border-bottom-color: var(--hms-border, rgba(255,255,255,0.1)) !important;
        }
        .theme-dark .modal-footer {
            border-top-color: var(--hms-border, rgba(255,255,255,0.1)) !important;
        }
        .theme-dark .modal-title {
            color: #fff !important;
        }
    </style>
</head>
<body class="skin-blue">
    <?php require_once(APPPATH.'views/include/header.php');?>

    <div class="wrapper row-offcanvas row-offcanvas-left">
        <?php require_once(APPPATH.'views/include/sidebar.php');?>

        <aside class="right-side">
            <section class="content-header">
                <h1><i class="fa fa-user"></i> Patient Prescriptions</h1>
                <ol class="breadcrumb">
                    <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                    <li><a href="<?php echo base_url()?>app/pharmacy"><i class="fa fa-medkit"></i> Pharmacy</a></li>
                    <li class="active">Patient Detail</li>
                </ol>
            </section>

            <section class="content">
                <?php echo isset($message) ? $message : ''; ?>

                <!-- Patient Header -->
                <div class="patient-header">
                    <div class="row">
                        <div class="col-md-8">
                            <h2><i class="fa fa-user-circle"></i> <?php echo htmlspecialchars($patient_info->patient_name); ?></h2>
                            <div class="patient-meta">
                                <span><i class="fa fa-id-card"></i> <?php echo htmlspecialchars($patient_info->patient_no); ?></span> &nbsp;|&nbsp;
                                <span><i class="fa fa-file-text"></i> IOP: <?php echo htmlspecialchars($patient_info->iop_id); ?></span> &nbsp;|&nbsp;
                                <span><i class="fa fa-calendar"></i> <?php echo htmlspecialchars($patient_info->date_visit); ?></span> &nbsp;|&nbsp;
                                <span><i class="fa fa-phone"></i> <?php echo htmlspecialchars($patient_info->mobile_no ?: 'N/A'); ?></span>
                            </div>
                        </div>
                        <div class="col-md-4 text-right">
                            <?php if ($patient_info->payer_type === 'NHIS'): ?>
                                <span class="label label-info" style="font-size: 16px; padding: 8px 15px;"><i class="fa fa-shield"></i> NHIS Patient</span>
                            <?php else: ?>
                                <span class="label label-default" style="font-size: 16px; padding: 8px 15px;"><i class="fa fa-money"></i> Cash Patient</span>
                            <?php endif; ?>
                            <?php
                                /* Phase 6B — aggregate priority from prescription lines */
                                $hasUrgent = false; $hasStat = false; $hasPrn = false;
                                foreach ($prescriptions as $_rx) {
                                    if (!empty($_rx->is_urgent)) $hasUrgent = true;
                                    if (!empty($_rx->is_prn))    $hasPrn    = true;
                                    if (strtoupper(trim((string)($_rx->freq_code ?? ''))) === 'STAT') $hasStat = true;
                                }
                            ?>
                            <?php if ($hasUrgent): ?>
                                <br><span class="priority-badge urgent"><i class="fa fa-bolt"></i> URGENT ORDER</span>
                            <?php elseif ($hasStat): ?>
                                <br><span class="priority-badge stat"><i class="fa fa-exclamation-triangle"></i> STAT</span>
                            <?php elseif ($hasPrn): ?>
                                <br><span class="priority-badge prn">PRN</span>
                            <?php endif; ?>
                            <br><br>
                            <a href="<?php echo base_url(); ?>app/pharmacy" class="btn btn-default"><i class="fa fa-arrow-left"></i> Back to Worklist</a>
                            <a href="<?php echo base_url(); ?>app/patient_history/<?php echo rawurlencode((string)$patient_info->patient_no); ?>" class="btn btn-default" style="margin-left:6px;"><i class="fa fa-history"></i> Patient History</a>
                        </div>
                    </div>
                </div>

				<?php
					$pharmacy_return_url = base_url() . 'app/pharmacy/patient/' . url_safe_id($iop_id);
					$pendingVerifyIds = array();
					$verifiedForBilling = 0;
					foreach ($prescriptions as $_rx) {
						$_rxStatus = isset($_rx->prescription_status) ? strtoupper(trim((string)$_rx->prescription_status)) : 'PENDING';
						$_dispStatus = isset($_rx->status_label) ? strtoupper(trim((string)$_rx->status_label)) : '';
						if ($_rxStatus === 'VERIFIED' && !in_array($_dispStatus, array('UNAVAILABLE','EXTERNAL','CANCELLED'), true)) {
							$verifiedForBilling++;
						}
						if ($_rxStatus !== 'VERIFIED' && !in_array($_rxStatus, array('ON_HOLD','CANCELLED'), true) && $_dispStatus !== 'CANCELLED') {
							$pendingVerifyIds[] = (int)$_rx->iop_med_id;
						}
					}
				?>
				<?php if (function_exists('has_role') && (has_role('admin') || has_role('pharmacist'))): ?>
				<div class="quick-actions">
					<h4><i class="fa fa-check-square-o"></i> Verification & Billing Finalization</h4>
					<div class="row">
						<div class="col-md-8">
							<p class="text-muted" style="margin-bottom:10px;">Verify prescriptions first, then explicitly finalize verified items for cashier billing.</p>
						</div>
						<div class="col-md-4 text-right">
							<?php if (count($pendingVerifyIds) > 0): ?>
								<form method="post" action="<?php echo base_url(); ?>app/pharmacy/bulk_verify_prescriptions" class="ajax-bulk-verify-form" style="display:inline-block;">
									<input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
									<input type="hidden" name="notes" value="Bulk pharmacy verification">
									<?php foreach ($pendingVerifyIds as $_mid): ?>
										<input type="hidden" name="iop_med_ids[]" value="<?php echo (int)$_mid; ?>">
									<?php endforeach; ?>
									<button type="submit" class="btn btn-primary" id="btnBulkVerify"><i class="fa fa-check-square-o"></i> Verify All Pending (<?php echo count($pendingVerifyIds); ?>)</button>
								</form>
							<?php endif; ?>
							<form method="post" action="<?php echo base_url(); ?>app/pharmacy/finalize_for_billing" style="display:inline-block; margin-left:6px;">
								<input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
								<input type="hidden" name="iop_id" value="<?php echo htmlspecialchars($iop_id); ?>">
								<input type="hidden" name="patient_no" value="<?php echo htmlspecialchars($patient_info->patient_no); ?>">
								<input type="hidden" name="return_url" value="<?php echo htmlspecialchars($pharmacy_return_url); ?>">
								<button type="submit" class="btn btn-success" id="btnFinalizeForBilling" <?php echo $verifiedForBilling <= 0 ? 'disabled' : ''; ?> onclick="return confirm('Finalize verified prescriptions for cashier billing?');"><i class="fa fa-money"></i> Finalize for Billing</button>
							</form>
						</div>
					</div>
				</div>
				<?php endif; ?>

				<?php $vd = isset($visit_details) ? $visit_details : null; ?>
				<?php if (is_array($vd) && isset($vd['visit'])): ?>
				<div class="box box-default" style="border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.08);border:none;">
					<div class="box-header with-border">
						<h3 class="box-title"><i class="fa fa-stethoscope"></i> Clinical Summary (Read-only)</h3>
					</div>
					<div class="box-body">
						<div class="row">
							<div class="col-md-6">
								<strong>Chief Complaints</strong>
								<?php if (isset($vd['complaints']) && is_array($vd['complaints']) && count($vd['complaints']) > 0): ?>
									<ul style="margin-top:6px;">
										<?php foreach ($vd['complaints'] as $_c): ?>
											<li><?php echo htmlspecialchars((string)(($_c['complain_name'] ?? '') !== '' ? $_c['complain_name'] : ($_c['complain_text'] ?? ''))); ?><?php echo !empty($_c['remarks']) ? ' — '.htmlspecialchars((string)$_c['remarks']) : ''; ?></li>
										<?php endforeach; ?>
									</ul>
								<?php else: ?>
									<div class="text-muted" style="margin-top:6px;">No complaints recorded.</div>
								<?php endif; ?>
							</div>
							<div class="col-md-6">
								<strong>Diagnosis</strong>
								<?php if (isset($vd['diagnoses']) && is_array($vd['diagnoses']) && count($vd['diagnoses']) > 0): ?>
									<ul style="margin-top:6px;">
										<?php foreach ($vd['diagnoses'] as $_d): ?>
											<li><?php echo htmlspecialchars((string)(($_d['diagnosis_name'] ?? '') !== '' ? $_d['diagnosis_name'] : ($_d['diagnosis_text'] ?? ''))); ?><?php echo !empty($_d['remarks']) ? ' — '.htmlspecialchars((string)$_d['remarks']) : ''; ?></li>
										<?php endforeach; ?>
									</ul>
								<?php else: ?>
									<div class="text-muted" style="margin-top:6px;">No diagnosis recorded.</div>
								<?php endif; ?>
							</div>
						</div>
					</div>
				</div>
				<?php endif; ?>

				<?php
					$canFullPharmacy = (function_exists('has_privilege') && has_privilege('pharmacy_access'))
						|| (function_exists('has_role') && (has_role('admin') || has_role('pharmacist')));
				?>

                <!-- Quick Actions -->
                <?php
                    $canBulkDispense = false;
                    $allResolved = true;
                    $totalItems = count($prescriptions);
                    $dispensedCount = 0;      // Actually dispensed internally
                    $externalCount = 0;       // External purchase (patient buys outside)
                    $otherResolvedCount = 0;  // Waived, cancelled, etc.
                    $pendingCount = 0;        // Still pending
                    $pendingPayment = 0;
                    
                    // Statuses that count as "resolved" for medication clearance
                    $resolvedStatuses = array('DISPENSED', 'EXTERNAL', 'WAIVED', 'CANCELLED', 'UNABLE_TO_PAY');
                    
                    foreach ($prescriptions as $rx) {
                        if ($rx->can_dispense && $rx->remaining_qty > 0) $canBulkDispense = true;
                        
                        // Check if item is resolved (dispensed, external purchase, waived, etc.)
                        $isResolved = in_array($rx->status_label, $resolvedStatuses) || $rx->remaining_qty <= 0;
                        if (!$isResolved) $allResolved = false;
                        
                        // Count by actual status (SINGLE SOURCE OF TRUTH from status_label)
                        if ($rx->status_label === 'DISPENSED') {
                            $dispensedCount++;
                        } elseif ($rx->status_label === 'EXTERNAL') {
                            $externalCount++;
                        } elseif (in_array($rx->status_label, array('WAIVED', 'CANCELLED', 'UNABLE_TO_PAY'))) {
                            $otherResolvedCount++;
                        } else {
                            $pendingCount++;
                        }
                        
                        $payStatus = strtoupper(trim((string)$rx->payment_status));
                        $extStatus = strtoupper(trim((string)$rx->extended_status));
                        $isExternalItem = in_array($extStatus, array('EXTERNAL_PURCHASE', 'EXTERNAL')) || $rx->status_label === 'EXTERNAL';
                        $isDispensedItem = ($rx->status_label === 'DISPENSED');
                        $isNhisItem = (isset($patient_info->payer_type) && strtoupper($patient_info->payer_type) === 'NHIS') || (isset($rx->is_nhis_covered) && $rx->is_nhis_covered);
                        
                        // Only count as pending payment if: not paid, not waived, not exception, not external, not dispensed, not NHIS
                        if ($payStatus !== 'PAID' && $payStatus !== 'WAIVED' && !$rx->is_exception && !$isExternalItem && !$isDispensedItem && !$isNhisItem) {
                            $pendingPayment++;
                        }
                    }
                    
                    // Total resolved = dispensed + external + other resolved
                    $totalResolved = $dispensedCount + $externalCount + $otherResolvedCount;

                    $medCleared = (isset($medication_cleared) && $medication_cleared);
                    $clearanceBtnAttr = '';
                    if (!$allResolved) {
                        $clearanceBtnAttr = 'disabled title="Not all medications resolved"';
                    } elseif ($medCleared) {
                        $clearanceBtnAttr = 'disabled title="Medication clearance already recorded"';
                    }
                ?>
                <div class="quick-actions">
                    <div class="row">
                        <div class="col-md-6">
                            <h4><i class="fa fa-bolt"></i> Quick Actions</h4>
							<?php if ($canFullPharmacy): ?>
                            <form method="post" action="<?php echo base_url(); ?>app/pharmacy/bulk_dispense" style="display: inline;">
                                <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                                <input type="hidden" name="iop_id" value="<?php echo htmlspecialchars($iop_id); ?>">
                                <button type="submit" class="btn btn-success btn-lg" <?php echo !$canBulkDispense ? 'disabled' : ''; ?> onclick="return confirm('Dispense ALL eligible medications for this patient?');">
                                    <i class="fa fa-check-double"></i> Dispense All Eligible
                                </button>
                            </form>
                            <form method="post" action="<?php echo base_url(); ?>app/pharmacy/patient_clearance" style="display: inline; margin-left: 10px;">
                                <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                                <input type="hidden" name="iop_id" value="<?php echo htmlspecialchars($iop_id); ?>">
                                <input type="hidden" name="patient_no" value="<?php echo htmlspecialchars($patient_info->patient_no); ?>">
                                <button type="submit" class="btn btn-primary btn-lg" <?php echo $clearanceBtnAttr; ?> onclick="return confirm('Mark medication clearance for this patient?');">
                                    <i class="fa fa-flag-checkered"></i> Medication Clearance
                                </button>
                            </form>
							<?php endif; ?>
                        </div>
                        <div class="col-md-6 text-right">
                            <div class="btn-group">
                                <span class="btn btn-default disabled"><i class="fa fa-list"></i> <?php echo $totalItems; ?> Items</span>
                                <?php if ($dispensedCount > 0): ?>
                                    <span class="btn btn-success disabled"><i class="fa fa-check"></i> <?php echo $dispensedCount; ?> Dispensed</span>
                                <?php endif; ?>
                                <?php if ($externalCount > 0): ?>
                                    <span class="btn btn-info disabled"><i class="fa fa-external-link"></i> <?php echo $externalCount; ?> External</span>
                                <?php endif; ?>
                                <?php if ($pendingCount > 0): ?>
                                    <span class="btn btn-warning disabled"><i class="fa fa-clock-o"></i> <?php echo $pendingCount; ?> Pending</span>
                                <?php endif; ?>
                                <?php if ($pendingPayment > 0): ?>
                                    <span class="btn btn-danger disabled"><i class="fa fa-exclamation-circle"></i> <?php echo $pendingPayment; ?> Awaiting Payment</span>
                                <?php endif; ?>
                                <?php if ($allResolved && $totalItems > 0): ?>
                                    <span class="btn btn-success disabled"><i class="fa fa-check-circle"></i> All Resolved</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Prescriptions List -->
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-pills"></i> Prescriptions</h3>
                    </div>
                    <div class="box-body">
                        <?php if (!isset($prescriptions) || count($prescriptions) === 0): ?>
                            <div class="alert alert-info">
                                <i class="fa fa-info-circle"></i> No prescriptions found for this patient.
                            </div>
                        <?php else: ?>
                            <?php foreach ($prescriptions as $rx): ?>
                                <?php
                                    $statusClass = strtolower($rx->status_label);
                                    $payStatus = strtoupper(trim((string)$rx->payment_status));
                                    $extStatus = strtoupper(trim((string)$rx->extended_status));
								$rxStatus = strtoupper(trim((string)($rx->prescription_status ?? 'VERIFIED')));
								if ($rxStatus === '') { $rxStatus = 'VERIFIED'; }
								$isVerified = ($rxStatus === 'VERIFIED');
                                    $prescribedQty = isset($rx->prescribed_total_qty) ? (float)$rx->prescribed_total_qty : (float)$rx->total_qty;
                                    $approvedQty = isset($rx->approved_qty) ? (float)$rx->approved_qty : (float)$rx->total_qty;
                                    $billableQty = isset($rx->billable_qty) ? (float)$rx->billable_qty : $approvedQty;
                                    $hasAdjustment = !empty($rx->has_pharmacy_adjustment);
                                    $progressPct = ($rx->total_qty > 0) ? round(($rx->dispensed_qty / $rx->total_qty) * 100) : 0;
                                    $progressClass = 'progress-bar-danger';
                                    if ($progressPct >= 100) $progressClass = 'progress-bar-success';
                                    elseif ($progressPct > 0) $progressClass = 'progress-bar-warning';
                                ?>
                                <div class="rx-item status-<?php echo $statusClass; ?>" id="rxItem<?php echo (int)$rx->iop_med_id; ?>" data-verified="<?php echo $isVerified ? '1' : '0'; ?>">
                                    <!-- Phase 6B: RX meta strip -->
                                    <div class="rx-meta-strip">
                                        <?php if (!empty($rx->prescription_no)): ?>
                                            <span><i class="fa fa-barcode"></i> <strong><?php echo htmlspecialchars($rx->prescription_no); ?></strong></span>
                                        <?php endif; ?>
                                        <?php if (!empty($rx->dDate)): ?>
                                            <span><i class="fa fa-clock-o"></i> <?php echo htmlspecialchars(substr($rx->dDate, 0, 16)); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($rx->doctor_id ?? '')): ?>
                                            <span><i class="fa fa-user-md"></i> <?php echo htmlspecialchars($rx->doctor_id); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($rx->is_urgent)): ?>
                                            <span class="priority-badge urgent" style="padding:3px 10px;font-size:11px;"><i class="fa fa-bolt"></i> URGENT</span>
                                        <?php endif; ?>
                                        <?php if (!empty($rx->is_prn)): ?>
                                            <span class="priority-badge prn" style="padding:3px 10px;font-size:11px;">PRN</span>
                                        <?php endif; ?>
                                        <?php if (isset($rx->freq_code) && strtoupper(trim($rx->freq_code)) === 'STAT'): ?>
                                            <span class="priority-badge stat" style="padding:3px 10px;font-size:11px;">STAT</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="rx-header">
                                        <div>
                                            <div class="rx-drug-name">
                                                <?php 
                                                    // Get drug name with multiple fallbacks
                                                    $drugDisplayName = '';
                                                    if (!empty($rx->drug_name)) {
                                                        $drugDisplayName = $rx->drug_name;
                                                    } elseif (!empty($rx->medicine_text)) {
                                                        $drugDisplayName = $rx->medicine_text;
                                                    } else {
                                                        // Try to get from medicine_id lookup
                                                        $drugDisplayName = '[Drug ID: ' . (int)$rx->medicine_id . ']';
                                                    }
                                                    echo htmlspecialchars($drugDisplayName);
                                                ?>
                                                <?php 
                                                    // NHIS Mapping Badge - only show if drug name is displayed
                                                    $nhisCode = isset($rx->nhis_drug_code) ? trim((string)$rx->nhis_drug_code) : '';
                                                    if (!empty($nhisCode)): 
                                                ?>
                                                    <span class="label label-success" title="NHIS Mapped: <?php echo htmlspecialchars($nhisCode); ?>" style="font-size: 10px; margin-left: 5px;"><i class="fa fa-check-circle"></i> NHIS</span>
                                                <?php else: ?>
                                                    <span class="label label-danger" title="Not mapped to NHIS tariff" style="font-size: 10px; margin-left: 5px;"><i class="fa fa-times-circle"></i> NO NHIS</span>
                                                <?php endif; ?>
                                                <?php if ($rx->is_nhis_covered): ?>
                                                    <span class="label label-info" title="NHIS Covered"><i class="fa fa-shield"></i></span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (!empty($rx->prescription_no)): ?>
                                            <div style="margin:3px 0 4px;">
                                                <span style="font-family:monospace;font-size:12px;background:#e9ecef;padding:2px 8px;border-radius:4px;color:#495057;font-weight:700;letter-spacing:.5px;">
                                                    <i class="fa fa-barcode"></i> <?php echo htmlspecialchars($rx->prescription_no); ?>
                                                </span>
                                                <?php if (!empty($rx->is_urgent)): ?>
                                                    <span class="label label-danger" style="font-size:10px;margin-left:4px;"><i class="fa fa-bolt"></i> URGENT</span>
                                                <?php endif; ?>
                                                <?php if (!empty($rx->is_prn)): ?>
                                                    <span class="label label-warning" style="font-size:10px;margin-left:4px;">PRN</span>
                                                <?php endif; ?>
                                            </div>
                                            <?php endif; ?>
                                            <div class="rx-details">
                                                <span><i class="fa fa-calendar"></i> <?php echo htmlspecialchars($rx->dDate); ?></span> &nbsp;|&nbsp;
                                                <span><i class="fa fa-calendar-check-o"></i> <?php echo (int)$rx->days; ?> days</span>
                                                &nbsp;|&nbsp; <span><i class="fa fa-cubes"></i> Qty: <?php echo htmlspecialchars(number_format((float)$rx->total_qty, 2)); ?></span>
                                                &nbsp;|&nbsp; <span><i class="fa fa-hourglass-half"></i> Remaining: <?php echo (int)$rx->remaining_qty; ?></span>
                                                <?php if (!empty($rx->drug_form)): ?>
                                                &nbsp;|&nbsp; <span><i class="fa fa-medkit"></i> <?php echo htmlspecialchars($rx->drug_form); ?></span>
                                                <?php endif; ?>
                                                <?php if (!empty($rx->dosage)): ?>
                                                &nbsp;|&nbsp; <span><i class="fa fa-flask"></i> <?php echo htmlspecialchars($rx->dosage); ?><?php echo !empty($rx->unit) ? ' '.htmlspecialchars($rx->unit) : ''; ?></span>
                                                <?php endif; ?>
                                                <?php if (!empty($rx->frequency)): ?>
                                                &nbsp;|&nbsp; <span><i class="fa fa-repeat"></i> <?php echo htmlspecialchars($rx->frequency); ?></span>
                                                <?php endif; ?>
                                                <?php if (!empty($rx->route)): ?>
                                                &nbsp;|&nbsp; <span><i class="fa fa-tint"></i> <?php echo htmlspecialchars($rx->route); ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="rx-details" style="margin-top:6px;">
                                                <span><strong>Prescribed:</strong> <?php echo htmlspecialchars(number_format($prescribedQty, 2)); ?> unit(s)</span>
                                                &nbsp;|&nbsp; <span><strong>Approved:</strong> <?php echo htmlspecialchars(number_format($approvedQty, 2)); ?> unit(s)</span>
                                                &nbsp;|&nbsp; <span><strong>Billable:</strong> <?php echo htmlspecialchars(number_format($billableQty, 2)); ?> unit(s)</span>
                                                &nbsp;|&nbsp; <span><strong>Unit price:</strong> <?php echo htmlspecialchars(number_format((float)$rx->nPrice, 2)); ?></span>
                                                <?php if ($hasAdjustment): ?>
                                                    &nbsp; <span class="label label-info" title="<?php echo htmlspecialchars((string)$rx->pharmacy_adjustment_reason); ?>">Adjusted</span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (!empty($rx->instruction) || !empty($rx->advice)): ?>
                                                <div style="margin-top:8px;font-size:13px;color:#2c3e50;">
                                                    <?php if (!empty($rx->instruction)): ?>
                                                        <div><i class="fa fa-edit" style="color:#6c757d;"></i> <strong>Instruction:</strong> <?php echo htmlspecialchars($rx->instruction); ?></div>
                                                    <?php endif; ?>
                                                    <?php if (!empty($rx->advice)): ?>
                                                        <div><i class="fa fa-info-circle" style="color:#6c757d;"></i> <strong>Advice:</strong> <?php echo htmlspecialchars($rx->advice); ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-right">
                                            <?php 
                                                // Determine statuses for display
                                                $isExternal = in_array($extStatus, array('EXTERNAL_PURCHASE', 'EXTERNAL')) || $rx->status_label === 'EXTERNAL';
                                                $isDispensed = ($rx->status_label === 'DISPENSED');
                                                $isPartial = ($rx->status_label === 'PARTIAL');
                                                $isWaived = in_array($extStatus, array('WAIVED', 'WAIVER_APPROVED')) || $payStatus === 'WAIVED';
                                                $isPaid = ($payStatus === 'PAID');
                                                $isNhis = (isset($patient_info->payer_type) && strtoupper($patient_info->payer_type) === 'NHIS') || (isset($rx->is_nhis_covered) && $rx->is_nhis_covered);
                                                
                                                // Determine combined status for clearer display
                                                // Priority: DISPENSED > EXTERNAL > PARTIAL > PENDING
                                            ?>
                                            <?php if ($isDispensed): ?>
                                                <span class="label label-success" style="font-size: 13px;"><i class="fa fa-check-circle"></i> DISPENSED</span>
                                            <?php elseif ($isExternal): ?>
                                                <span class="label label-info" style="font-size: 13px;"><i class="fa fa-external-link"></i> EXTERNAL</span>
                                            <?php elseif ($isPartial): ?>
                                                <span class="label label-warning" style="font-size: 13px;"><i class="fa fa-adjust"></i> PARTIAL</span>
                                            <?php elseif ($isWaived): ?>
                                                <span class="label label-success" style="font-size: 13px;"><i class="fa fa-gift"></i> WAIVED</span>
                                            <?php elseif ($rx->status_label === 'UNAVAILABLE'): ?>
                                                <span class="label label-default" style="font-size: 13px;"><i class="fa fa-ban"></i> UNAVAILABLE</span>
                                            <?php elseif ($rx->status_label === 'ON_HOLD' || $rxStatus === 'ON_HOLD'): ?>
                                                <span class="label label-default" style="font-size: 13px;"><i class="fa fa-pause"></i> ON HOLD</span>
                                            <?php elseif ($extStatus === 'UNABLE_TO_PAY'): ?>
                                                <span class="label label-warning" style="font-size: 13px;"><i class="fa fa-user-times"></i> UNABLE TO PAY</span>
                                            <?php elseif ($rx->status_label === 'CANCELLED' || $rxStatus === 'CANCELLED' || $extStatus === 'CANCELLED'): ?>
                                                <span class="label label-default" style="font-size: 13px;"><i class="fa fa-times"></i> CANCELLED</span>
                                            <?php else: ?>
                                                <!-- PENDING status - show dispensing status + payment status separately -->
                                                <span class="label label-default" style="font-size: 13px;"><i class="fa fa-clock-o"></i> PENDING</span>
                                            <?php endif; ?>

									<?php if (!$isVerified && $rx->status_label !== 'DISPENSED' && $rx->status_label !== 'EXTERNAL' && $rx->status_label !== 'CANCELLED'): ?>
										<br>
										<span class="label label-warning" style="font-size: 11px; margin-top: 6px; display: inline-block;"><i class="fa fa-exclamation-triangle"></i> RX: <?php echo htmlspecialchars($rxStatus); ?></span>
									<?php endif; ?>
                                            <br><br>
                                            <!-- Payment Status Badge (only show for non-completed items) -->
                                            <?php if (!$isDispensed && !$isExternal && !$isWaived && $rx->status_label !== 'CANCELLED'): ?>
                                                <?php if ($isPaid): ?>
                                                    <span class="label label-success payment-badge"><i class="fa fa-check"></i> PAID</span>
                                                <?php elseif ($isNhis): ?>
                                                    <span class="label label-success payment-badge"><i class="fa fa-shield"></i> NHIS</span>
                                                <?php elseif ($extStatus === 'DEFERRED'): ?>
                                                    <span class="label label-warning payment-badge"><i class="fa fa-calendar"></i> DEFERRED</span>
                                                <?php elseif ($extStatus === 'EMERGENCY'): ?>
                                                    <span class="label label-danger payment-badge"><i class="fa fa-ambulance"></i> EMERGENCY</span>
                                                <?php elseif ($extStatus === 'WAIVER_REQUESTED'): ?>
                                                    <span class="label label-info payment-badge"><i class="fa fa-hourglass-half"></i> WAIVER PENDING</span>
                                                <?php elseif ($extStatus !== 'UNABLE_TO_PAY'): ?>
                                                    <span class="label label-danger payment-badge"><i class="fa fa-exclamation-circle"></i> UNPAID</span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Progress Bar -->
                                    <div class="rx-progress">
                                        <div class="progress">
                                            <div class="progress-bar <?php echo $progressClass; ?>" role="progressbar" style="width: <?php echo $progressPct; ?>%;">
                                                <?php echo (int)$rx->dispensed_qty; ?> / <?php echo (int)$rx->total_qty; ?>
                                            </div>
                                        </div>
                                        <div>
                                            <strong>Stock:</strong>
                                            <?php if ($rx->out_of_stock): ?>
                                                <span class="stock-warning"><i class="fa fa-times-circle"></i> OUT OF STOCK</span>
                                            <?php elseif ($rx->stock_low): ?>
                                                <span class="stock-warning"><i class="fa fa-exclamation-triangle"></i> <?php echo (int)$rx->current_stock; ?></span>
                                            <?php else: ?>
                                                <span class="text-success"><?php echo (int)$rx->current_stock; ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Actions -->
                                    <?php if ($rx->status_label !== 'DISPENSED' && $rx->status_label !== 'EXTERNAL' && $rx->status_label !== 'CANCELLED'): ?>
                                        <div class="rx-actions">
										<?php if (!$isVerified): ?>
											<div class="alert alert-warning" style="padding: 8px 12px; margin-bottom: 10px;">
												<i class="fa fa-exclamation-triangle"></i> <strong>Verification Required</strong> — Verify prescription before dispensing or billing.
											</div>
										<?php endif; ?>

                                            <?php if (!$rx->can_dispense && !$rx->is_exception && $payStatus !== 'PAID' && $payStatus !== 'WAIVED'): ?>
                                                <div class="alert alert-danger" style="padding: 8px 12px; margin-bottom: 10px;">
                                                    <i class="fa fa-lock"></i> <strong>Payment Required</strong> — Direct patient to cashier before dispensing.
                                                </div>
                                            <?php endif; ?>

                                            <div class="action-btn-group">
											<?php if (function_exists('has_role') && (has_role('admin') || has_role('pharmacist'))): ?>
												<?php if (!$isVerified && $rxStatus !== 'ON_HOLD' && $rxStatus !== 'CANCELLED'): ?>
													<button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#verifyModal<?php echo (int)$rx->iop_med_id; ?>">
														<i class="fa fa-check"></i> Verify
													</button>
												<?php endif; ?>
												<?php if (($rxStatus === 'PENDING' || $rxStatus === 'VERIFIED') && (float)$rx->dispensed_qty <= 0): ?>
													<button type="button" class="btn btn-info btn-sm" data-toggle="modal" data-target="#substituteModal<?php echo (int)$rx->iop_med_id; ?>">
														<i class="fa fa-exchange"></i> Substitute
													</button>
													<button type="button" class="btn btn-warning btn-sm" data-toggle="modal" data-target="#adjustModal<?php echo (int)$rx->iop_med_id; ?>">
														<i class="fa fa-sliders"></i> Adjust
													</button>
												<?php endif; ?>
												<?php if ($rxStatus !== 'CANCELLED' && $rx->status_label !== 'DISPENSED' && $rx->status_label !== 'EXTERNAL'): ?>
													<?php if ($rxStatus !== 'ON_HOLD' && $rxStatus !== 'CANCELLED'): ?>
														<button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#holdModal<?php echo (int)$rx->iop_med_id; ?>">
															<i class="fa fa-pause"></i> Hold
														</button>
													<?php else: ?>
														<button type="button" class="btn btn-info btn-sm" data-toggle="modal" data-target="#resumeModal<?php echo (int)$rx->iop_med_id; ?>">
															<i class="fa fa-play"></i> Resume
														</button>
													<?php endif; ?>
													<button type="button" class="btn btn-danger btn-sm" data-toggle="modal" data-target="#cancelModal<?php echo (int)$rx->iop_med_id; ?>">
														<i class="fa fa-times"></i> Cancel
													</button>
												<?php endif; ?>
											<?php endif; ?>

                                                <!-- Dispense Buttons -->
                                                <?php if ($rx->remaining_qty > 0 && !$rx->out_of_stock): ?>
                                                    <button type="button" class="btn btn-success btn-sm" data-toggle="modal" data-target="#dispenseModal<?php echo (int)$rx->iop_med_id; ?>" <?php echo !$rx->can_dispense ? 'disabled' : ''; ?>>
                                                        <i class="fa fa-check"></i> Dispense
                                                    </button>
                                                    <button type="button" class="btn btn-warning btn-sm" data-toggle="modal" data-target="#partialModal<?php echo (int)$rx->iop_med_id; ?>" <?php echo !$rx->can_dispense ? 'disabled' : ''; ?>>
                                                        <i class="fa fa-adjust"></i> Partial
                                                    </button>
                                                <?php endif; ?>

                                                <!-- Flexible Workflow Buttons (only show if not already resolved) -->
                                                <?php if ($canFullPharmacy && $isVerified && $extStatus === '' && $payStatus !== 'PAID' && $payStatus !== 'WAIVED'): ?>
                                                    <div class="btn-group">
                                                        <button type="button" class="btn btn-default btn-sm dropdown-toggle" data-toggle="dropdown">
                                                            <i class="fa fa-cog"></i> Options <span class="caret"></span>
                                                        </button>
                                                        <ul class="dropdown-menu">
                                                            <li><a href="#" data-toggle="modal" data-target="#extPurchaseModal<?php echo (int)$rx->iop_med_id; ?>"><i class="fa fa-external-link"></i> External Purchase</a></li>
                                                            <li><a href="#" data-toggle="modal" data-target="#unableToPayModal<?php echo (int)$rx->iop_med_id; ?>"><i class="fa fa-user-times"></i> Unable to Pay</a></li>
                                                            <li><a href="#" data-toggle="modal" data-target="#deferredModal<?php echo (int)$rx->iop_med_id; ?>"><i class="fa fa-calendar"></i> Defer Payment</a></li>
                                                            <li class="divider"></li>
                                                            <li><a href="#" data-toggle="modal" data-target="#emergencyModal<?php echo (int)$rx->iop_med_id; ?>"><i class="fa fa-ambulance text-danger"></i> Emergency Override</a></li>
                                                            <li><a href="#" data-toggle="modal" data-target="#waiverModal<?php echo (int)$rx->iop_med_id; ?>"><i class="fa fa-gift"></i> Request Waiver</a></li>
                                                            <?php if ($rx->status_label !== 'UNAVAILABLE'): ?>
                                                                <li class="divider"></li>
                                                                <li><a href="#" data-toggle="modal" data-target="#unavailableModal<?php echo (int)$rx->iop_med_id; ?>"><i class="fa fa-ban text-danger"></i> Mark Unavailable</a></li>
                                                            <?php endif; ?>
                                                        </ul>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if ($canFullPharmacy && $rx->status_label === 'UNAVAILABLE'): ?>
                                                    <form method="post" action="<?php echo base_url(); ?>app/pharmacy/mark_available" style="display: inline;">
                                                        <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                                                        <input type="hidden" name="iop_med_id" value="<?php echo (int)$rx->iop_med_id; ?>">
                                                        <input type="hidden" name="return_url" value="<?php echo base_url(); ?>app/pharmacy/patient/<?php echo url_safe_id($iop_id); ?>">
                                                        <button type="submit" class="btn btn-info btn-sm" onclick="return confirm('Restore this medication to PENDING?');">
                                                            <i class="fa fa-undo"></i> Mark Available
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- Modals for this prescription -->
                                        <?php $return_url = base_url() . 'app/pharmacy/patient/' . url_safe_id($iop_id); ?>

										<!-- Verify Modal -->
										<div class="modal fade" id="verifyModal<?php echo (int)$rx->iop_med_id; ?>" tabindex="-1">
											<div class="modal-dialog">
												<div class="modal-content">
													<form method="post" action="<?php echo base_url(); ?>app/pharmacy/verify_prescription/<?php echo (int)$rx->iop_med_id; ?>" class="ajax-verify-one-form">
														<input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
														<input type="hidden" name="return_url" value="<?php echo htmlspecialchars($return_url); ?>">
														<input type="hidden" name="defer_billing" value="1">
														<div class="modal-header bg-blue">
															<button type="button" class="close" data-dismiss="modal">&times;</button>
															<h4 class="modal-title"><i class="fa fa-check"></i> Verify Prescription</h4>
														</div>
														<div class="modal-body">
															<p><strong>Drug:</strong> <?php echo htmlspecialchars($rx->drug_name ?: $rx->medicine_text); ?></p>
															<div class="form-group">
																<label>Notes <small class="text-muted">(optional)</small></label>
																<textarea class="form-control" name="notes" rows="2"></textarea>
															</div>
														</div>
														<div class="modal-footer">
															<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
															<button type="submit" class="btn btn-primary"><i class="fa fa-check"></i> Verify</button>
														</div>
													</form>
												</div>
											</div>
										</div>

										<div class="modal fade" id="substituteModal<?php echo (int)$rx->iop_med_id; ?>" tabindex="-1">
											<div class="modal-dialog">
												<div class="modal-content">
													<form method="post" action="<?php echo base_url(); ?>app/pharmacy/substitute_medication">
														<input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
														<input type="hidden" name="iop_med_id" value="<?php echo (int)$rx->iop_med_id; ?>">
														<input type="hidden" name="return_url" value="<?php echo htmlspecialchars($return_url); ?>">
														<div class="modal-header bg-info">
															<button type="button" class="close" data-dismiss="modal">&times;</button>
															<h4 class="modal-title"><i class="fa fa-exchange"></i> Substitute Medication</h4>
														</div>
														<div class="modal-body">
															<p><strong>Original:</strong> <?php echo htmlspecialchars($rx->drug_name ?: $rx->medicine_text); ?></p>
															<div class="form-group">
																<label>Search Substitute Drug</label>
																<input type="text" class="form-control substitute-drug-search" placeholder="Type drug name (min 2 characters)">
																<div class="substitute-drug-results" style="margin-top:8px;"></div>
																<div class="substitute-drug-selected text-info" style="margin-top:6px; display:none;"></div>
															</div>
															<div class="form-group">
																<label>Substitute Drug ID</label>
																<input type="number" class="form-control" name="substitute_drug_id" min="1" required>
																<p class="help-block">Enter the preferred drug ID (must be active and in stock).</p>
															</div>
															<div class="row">
    <div class="col-sm-6">
        <div class="form-group">
            <label>Total Quantity</label>
            <input type="number" class="form-control" name="total_qty" step="0.01" min="0.01" value="<?php echo htmlspecialchars((string)$rx->total_qty); ?>" required>
        </div>
    </div>
    <div class="col-sm-6">
        <div class="form-group">
            <label>Days</label>
            <input type="number" class="form-control" name="days" min="0" value="<?php echo htmlspecialchars((string)$rx->days); ?>">
        </div>
    </div>
</div>
<div class="row">
    <div class="col-sm-6">
        <div class="form-group">
            <label>Dosage</label>
            <input type="text" class="form-control" name="dosage" value="<?php echo htmlspecialchars((string)$rx->dosage); ?>">
        </div>
    </div>
    <div class="col-sm-6">
        <div class="form-group">
            <label>Unit</label>
            <input type="text" class="form-control" name="unit" value="<?php echo htmlspecialchars((string)$rx->unit); ?>">
        </div>
    </div>
</div>
<div class="row">
    <div class="col-sm-6">
        <div class="form-group">
            <label>Frequency</label>
            <input type="text" class="form-control" name="frequency" value="<?php echo htmlspecialchars((string)$rx->frequency); ?>">
        </div>
    </div>
    <div class="col-sm-6">
        <div class="form-group">
            <label>Route</label>
            <input type="text" class="form-control" name="route" value="<?php echo htmlspecialchars((string)$rx->route); ?>">
        </div>
    </div>
</div>
<div class="form-group">
    <label>Instructions</label>
    <textarea class="form-control" name="instruction" rows="2"><?php echo htmlspecialchars((string)$rx->instruction); ?></textarea>
</div>
<div class="form-group">
    <label>Advice</label>
    <textarea class="form-control" name="advice" rows="2"><?php echo htmlspecialchars((string)$rx->advice); ?></textarea>
</div>
<div class="form-group">
    <label>Reason</label>
    <textarea class="form-control" name="reason" rows="3" required></textarea>
</div>
														</div>
														<div class="modal-footer">
															<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
															<button type="submit" class="btn btn-info"><i class="fa fa-exchange"></i> Substitute</button>
														</div>
													</form>
												</div>
											</div>
										</div>

										<!-- Pharmacy Adjustment Modal -->
										<div class="modal fade" id="adjustModal<?php echo (int)$rx->iop_med_id; ?>" tabindex="-1">
											<div class="modal-dialog">
												<div class="modal-content">
													<form method="post" action="<?php echo base_url(); ?>app/pharmacy/adjust_prescription">
														<input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
														<input type="hidden" name="iop_med_id" value="<?php echo (int)$rx->iop_med_id; ?>">
														<input type="hidden" name="return_url" value="<?php echo htmlspecialchars($return_url); ?>">
														<div class="modal-header bg-warning">
															<button type="button" class="close" data-dismiss="modal">&times;</button>
															<h4 class="modal-title"><i class="fa fa-sliders"></i> Pharmacy Adjustment</h4>
														</div>
														<div class="modal-body">
															<p><strong>Drug:</strong> <?php echo htmlspecialchars($rx->drug_name ?: $rx->medicine_text); ?></p>
															<div class="row">
																<div class="col-sm-6">
																	<div class="form-group">
																		<label>Prescribed Qty</label>
																		<input type="text" class="form-control" value="<?php echo htmlspecialchars(number_format($prescribedQty, 2)); ?>" readonly>
																	</div>
																</div>
																<div class="col-sm-6">
																	<div class="form-group">
																		<label>Approved / Dispensible Qty</label>
																		<input type="number" class="form-control" name="approved_qty" step="0.01" min="0.01" value="<?php echo htmlspecialchars((string)$approvedQty); ?>" required>
																	</div>
																</div>
															</div>
															<div class="row">
																<div class="col-sm-6">
																	<div class="form-group">
																		<label>Billable Qty</label>
																		<input type="number" class="form-control" name="billable_qty" step="0.01" min="0.01" value="<?php echo htmlspecialchars((string)$billableQty); ?>" required>
																	</div>
																</div>
																<div class="col-sm-6">
																	<div class="form-group">
																		<label>Duration (Days)</label>
																		<input type="number" class="form-control" name="days" min="0" value="<?php echo htmlspecialchars((string)$rx->days); ?>">
																	</div>
																</div>
															</div>
															<div class="row">
																<div class="col-sm-6">
																	<div class="form-group">
																		<label>Dose</label>
																		<input type="text" class="form-control" name="dosage" value="<?php echo htmlspecialchars((string)$rx->dosage); ?>">
																	</div>
																</div>
																<div class="col-sm-6">
																	<div class="form-group">
																		<label>Frequency</label>
																		<input type="text" class="form-control" name="frequency" value="<?php echo htmlspecialchars((string)$rx->frequency); ?>">
																		<input type="hidden" name="freq_code" value="<?php echo htmlspecialchars((string)$rx->freq_code); ?>">
																	</div>
																</div>
															</div>
															<div class="form-group">
																<label>Reason / Comment</label>
																<textarea class="form-control" name="reason" rows="3" required><?php echo $hasAdjustment ? htmlspecialchars((string)$rx->pharmacy_adjustment_reason) : ''; ?></textarea>
															</div>
														</div>
														<div class="modal-footer">
															<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
															<button type="submit" class="btn btn-warning"><i class="fa fa-save"></i> Save Adjustment</button>
														</div>
													</form>
												</div>
											</div>
										</div>

										<!-- Hold Modal -->
										<div class="modal fade" id="holdModal<?php echo (int)$rx->iop_med_id; ?>" tabindex="-1">
											<div class="modal-dialog">
												<div class="modal-content">
													<form method="post" action="<?php echo base_url(); ?>app/pharmacy/hold_prescription/<?php echo (int)$rx->iop_med_id; ?>">
														<input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
														<input type="hidden" name="return_url" value="<?php echo htmlspecialchars($return_url); ?>">
														<div class="modal-header">
															<button type="button" class="close" data-dismiss="modal">&times;</button>
															<h4 class="modal-title"><i class="fa fa-pause"></i> Put Prescription On Hold</h4>
														</div>
														<div class="modal-body">
															<p><strong>Drug:</strong> <?php echo htmlspecialchars($rx->drug_name ?: $rx->medicine_text); ?></p>
															<div class="form-group">
																<label>Reason <small class="text-muted">(optional)</small></label>
																<textarea class="form-control" name="reason" rows="2"></textarea>
															</div>
														</div>
														<div class="modal-footer">
															<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
															<button type="submit" class="btn btn-default"><i class="fa fa-pause"></i> Hold</button>
														</div>
													</form>
												</div>
											</div>
										</div>

										<!-- Resume Modal -->
										<div class="modal fade" id="resumeModal<?php echo (int)$rx->iop_med_id; ?>" tabindex="-1">
											<div class="modal-dialog">
												<div class="modal-content">
													<form method="post" action="<?php echo base_url(); ?>app/pharmacy/resume_prescription/<?php echo (int)$rx->iop_med_id; ?>">
														<input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
														<input type="hidden" name="return_url" value="<?php echo htmlspecialchars($return_url); ?>">
														<div class="modal-header bg-info">
															<button type="button" class="close" data-dismiss="modal">&times;</button>
															<h4 class="modal-title"><i class="fa fa-play"></i> Resume Prescription</h4>
														</div>
														<div class="modal-body">
															<p><strong>Drug:</strong> <?php echo htmlspecialchars($rx->drug_name ?: $rx->medicine_text); ?></p>
															<div class="form-group">
																<label>Notes <small class="text-muted">(optional)</small></label>
																<textarea class="form-control" name="notes" rows="2"></textarea>
															</div>
														</div>
														<div class="modal-footer">
															<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
															<button type="submit" class="btn btn-info"><i class="fa fa-play"></i> Resume</button>
														</div>
													</form>
												</div>
											</div>
										</div>

										<!-- Cancel Modal -->
										<div class="modal fade" id="cancelModal<?php echo (int)$rx->iop_med_id; ?>" tabindex="-1">
											<div class="modal-dialog">
												<div class="modal-content">
													<form method="post" action="<?php echo base_url(); ?>app/pharmacy/cancel_prescription/<?php echo (int)$rx->iop_med_id; ?>">
														<input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
														<input type="hidden" name="return_url" value="<?php echo htmlspecialchars($return_url); ?>">
														<div class="modal-header bg-red">
															<button type="button" class="close" data-dismiss="modal">&times;</button>
															<h4 class="modal-title"><i class="fa fa-times"></i> Cancel Prescription</h4>
														</div>
														<div class="modal-body">
															<p><strong>Drug:</strong> <?php echo htmlspecialchars($rx->drug_name ?: $rx->medicine_text); ?></p>
															<div class="form-group">
																<label>Reason <span class="text-danger">*</span></label>
																<textarea class="form-control" name="reason" rows="2" required></textarea>
															</div>
														</div>
														<div class="modal-footer">
															<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
															<button type="submit" class="btn btn-danger"><i class="fa fa-times"></i> Cancel</button>
														</div>
													</form>
												</div>
											</div>
										</div>

                                        <!-- Dispense Modal (Phase 6B: AJAX) -->
                                        <div class="modal fade" id="dispenseModal<?php echo (int)$rx->iop_med_id; ?>" tabindex="-1"
                                             data-drug-id="<?php echo (int)$rx->medicine_id; ?>">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <form class="ajax-dispense-form">
                                                        <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                                                        <div class="modal-header bg-green">
                                                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                                                            <h4 class="modal-title"><i class="fa fa-check"></i> Dispense Medication</h4>
                                                        </div>
                                                        <div class="modal-body">
                                                            <input type="hidden" name="iop_med_id" value="<?php echo (int)$rx->iop_med_id; ?>">
                                                            <input type="hidden" name="iop_id" value="<?php echo htmlspecialchars($iop_id); ?>">
                                                            <input type="hidden" name="status" value="DISPENSED">
                                                            <p><strong>Drug:</strong> <?php echo htmlspecialchars($rx->drug_name ?: $rx->medicine_text); ?></p>
                                                            <div class="form-group">
                                                                <label>Quantity <small class="text-muted">(Remaining: <span class="lbl-remaining"><?php echo (int)$rx->remaining_qty; ?></span>, Stock: <span class="lbl-stock"><?php echo (int)$rx->current_stock; ?></span>, Paid Remaining: <span class="lbl-paid-remaining"><?php echo (float)(isset($rx->paid_remaining_qty) ? $rx->paid_remaining_qty : $rx->remaining_qty); ?></span>)</small></label>
                                                                <input type="number" step="0.01" min="0.01" max="<?php echo min($rx->remaining_qty, $rx->current_stock, (isset($rx->paid_remaining_qty) ? $rx->paid_remaining_qty : $rx->remaining_qty)); ?>" class="form-control" name="qty" value="<?php echo min($rx->remaining_qty, $rx->current_stock, (isset($rx->paid_remaining_qty) ? $rx->paid_remaining_qty : $rx->remaining_qty)); ?>" required>
                                                            </div>
                                                            <div class="form-group">
                                                                <label>Batch <small class="text-muted">(FEFO — earliest expiry first)</small></label>
                                                                <select class="form-control batch-select" name="batch_no">
                                                                    <option value="">Loading batches…</option>
                                                                </select>
                                                            </div>
                                                            <div class="form-group">
                                                                <label>Expiry Date <small class="text-muted">(auto-filled from batch)</small></label>
                                                                <input type="text" class="form-control batch-expiry" readonly placeholder="—">
                                                            </div>
                                                            <div class="form-group">
                                                                <label>Notes <small class="text-muted">(optional)</small></label>
                                                                <textarea class="form-control" name="notes" rows="2"></textarea>
                                                            </div>
                                                            <div class="ajax-modal-error alert alert-danger" style="display:none;"></div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-success ajax-submit-btn">
                                                                <i class="fa fa-check"></i>
                                                                <i class="fa fa-spinner fa-spin fa-spin-inline" style="display:none;"></i>
                                                                Dispense
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Partial Modal (Phase 6B: AJAX) -->
                                        <div class="modal fade" id="partialModal<?php echo (int)$rx->iop_med_id; ?>" tabindex="-1"
                                             data-drug-id="<?php echo (int)$rx->medicine_id; ?>">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <form class="ajax-dispense-form">
                                                        <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                                                        <div class="modal-header bg-yellow">
                                                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                                                            <h4 class="modal-title"><i class="fa fa-adjust"></i> Partial Dispense</h4>
                                                        </div>
                                                        <div class="modal-body">
                                                            <input type="hidden" name="iop_med_id" value="<?php echo (int)$rx->iop_med_id; ?>">
                                                            <input type="hidden" name="iop_id" value="<?php echo htmlspecialchars($iop_id); ?>">
                                                            <input type="hidden" name="status" value="PARTIAL">
                                                            <p><strong>Drug:</strong> <?php echo htmlspecialchars($rx->drug_name ?: $rx->medicine_text); ?></p>
                                                            <div class="form-group">
                                                                <label>Quantity <small class="text-muted">(Remaining: <span class="lbl-remaining"><?php echo (int)$rx->remaining_qty; ?></span>, Stock: <span class="lbl-stock"><?php echo (int)$rx->current_stock; ?></span>, Paid Remaining: <span class="lbl-paid-remaining"><?php echo (float)(isset($rx->paid_remaining_qty) ? $rx->paid_remaining_qty : $rx->remaining_qty); ?></span>)</small></label>
                                                                <input type="number" step="0.01" min="0.01" max="<?php echo min($rx->remaining_qty, $rx->current_stock, (isset($rx->paid_remaining_qty) ? $rx->paid_remaining_qty : $rx->remaining_qty)); ?>" class="form-control" name="qty" required>
                                                            </div>
                                                            <div class="form-group">
                                                                <label>Batch <small class="text-muted">(FEFO — earliest expiry first)</small></label>
                                                                <select class="form-control batch-select" name="batch_no">
                                                                    <option value="">Loading batches…</option>
                                                                </select>
                                                            </div>
                                                            <div class="form-group">
                                                                <label>Expiry Date <small class="text-muted">(auto-filled from batch)</small></label>
                                                                <input type="text" class="form-control batch-expiry" readonly placeholder="—">
                                                            </div>
                                                            <div class="form-group">
                                                                <label>Notes <small class="text-muted">(optional)</small></label>
                                                                <textarea class="form-control" name="notes" rows="2"></textarea>
                                                            </div>
                                                            <div class="ajax-modal-error alert alert-danger" style="display:none;"></div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-warning ajax-submit-btn">
                                                                <i class="fa fa-adjust"></i>
                                                                <i class="fa fa-spinner fa-spin fa-spin-inline" style="display:none;"></i>
                                                                Partial Dispense
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>

                                        <?php if ($canFullPharmacy): ?>

                                        <!-- External Purchase Modal -->
                                        <div class="modal fade" id="extPurchaseModal<?php echo (int)$rx->iop_med_id; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <form method="post" action="<?php echo base_url(); ?>app/pharmacy/mark_external_purchase">
                                                        <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                                                        <div class="modal-header bg-aqua">
                                                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                                                            <h4 class="modal-title"><i class="fa fa-external-link"></i> External Purchase</h4>
                                                        </div>
                                                        <div class="modal-body">
                                                            <input type="hidden" name="iop_med_id" value="<?php echo (int)$rx->iop_med_id; ?>">
                                                            <input type="hidden" name="return_url" value="<?php echo htmlspecialchars($return_url); ?>">
                                                            <p class="text-info"><i class="fa fa-info-circle"></i> Patient will purchase this medication externally. No payment required from cashier.</p>
                                                            <div class="form-group">
                                                                <label>Reason / Referral Note</label>
                                                                <textarea class="form-control" name="reason" rows="2" placeholder="e.g. Not in stock, patient referred to external pharmacy..."></textarea>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-info">Confirm External Purchase</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Unable to Pay Modal -->
                                        <div class="modal fade" id="unableToPayModal<?php echo (int)$rx->iop_med_id; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <form method="post" action="<?php echo base_url(); ?>app/pharmacy/mark_unable_to_pay">
                                                        <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                                                        <div class="modal-header bg-yellow">
                                                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                                                            <h4 class="modal-title"><i class="fa fa-user-times"></i> Unable to Pay</h4>
                                                        </div>
                                                        <div class="modal-body">
                                                            <input type="hidden" name="iop_med_id" value="<?php echo (int)$rx->iop_med_id; ?>">
                                                            <input type="hidden" name="return_url" value="<?php echo htmlspecialchars($return_url); ?>">
                                                            <div class="alert alert-warning"><i class="fa fa-exclamation-triangle"></i> Patient is unable to pay. Medication will be dispensed on humanitarian grounds.</div>
                                                            <div class="form-group">
                                                                <label>Reason</label>
                                                                <textarea class="form-control" name="reason" rows="2" placeholder="e.g. Patient indigent, social welfare case..."></textarea>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-warning">Confirm Unable to Pay</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Deferred Payment Modal -->
                                        <div class="modal fade" id="deferredModal<?php echo (int)$rx->iop_med_id; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <form method="post" action="<?php echo base_url(); ?>app/pharmacy/mark_deferred_payment">
                                                        <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                                                        <div class="modal-header">
                                                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                                                            <h4 class="modal-title"><i class="fa fa-calendar"></i> Deferred Payment</h4>
                                                        </div>
                                                        <div class="modal-body">
                                                            <input type="hidden" name="iop_med_id" value="<?php echo (int)$rx->iop_med_id; ?>">
                                                            <input type="hidden" name="return_url" value="<?php echo htmlspecialchars($return_url); ?>">
                                                            <p class="text-info">Payment is deferred to a future date. Medication can be dispensed now.</p>
                                                            <div class="form-group">
                                                                <label>Reason</label>
                                                                <textarea class="form-control" name="reason" rows="2" placeholder="e.g. Patient promised to pay on return visit..."></textarea>
                                                            </div>
                                                            <div class="form-group">
                                                                <label>Defer Until <small class="text-muted">(optional)</small></label>
                                                                <input type="date" class="form-control" name="defer_until" min="<?php echo date('Y-m-d'); ?>">
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-default">Confirm Deferred</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Emergency Override Modal -->
                                        <div class="modal fade" id="emergencyModal<?php echo (int)$rx->iop_med_id; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <form method="post" action="<?php echo base_url(); ?>app/pharmacy/mark_emergency_override">
                                                        <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                                                        <div class="modal-header bg-red">
                                                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                                                            <h4 class="modal-title"><i class="fa fa-ambulance"></i> Emergency Override</h4>
                                                        </div>
                                                        <div class="modal-body">
                                                            <input type="hidden" name="iop_med_id" value="<?php echo (int)$rx->iop_med_id; ?>">
                                                            <input type="hidden" name="return_url" value="<?php echo htmlspecialchars($return_url); ?>">
                                                            <div class="alert alert-danger"><strong><i class="fa fa-ambulance"></i> Emergency Override</strong><br>This will allow immediate dispensing without payment. All actions are logged for audit.</div>
                                                            <div class="form-group">
                                                                <label>Emergency Reason <span class="text-danger">*</span></label>
                                                                <textarea class="form-control" name="reason" rows="3" placeholder="Clinical justification for emergency override..." required></textarea>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-danger">Confirm Emergency Override</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Waiver Request Modal -->
                                        <div class="modal fade" id="waiverModal<?php echo (int)$rx->iop_med_id; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <form method="post" action="<?php echo base_url(); ?>app/pharmacy/request_waiver">
                                                        <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                                                        <div class="modal-header bg-purple">
                                                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                                                            <h4 class="modal-title"><i class="fa fa-gift"></i> Request Waiver</h4>
                                                        </div>
                                                        <div class="modal-body">
                                                            <input type="hidden" name="iop_med_id" value="<?php echo (int)$rx->iop_med_id; ?>">
                                                            <input type="hidden" name="return_url" value="<?php echo htmlspecialchars($return_url); ?>">
                                                            <div class="alert alert-info"><i class="fa fa-info-circle"></i> A waiver request will be sent to Admin for approval.</div>
                                                            <div class="form-group">
                                                                <label>Waiver Reason <span class="text-danger">*</span></label>
                                                                <textarea class="form-control" name="reason" rows="3" placeholder="Reason for requesting fee waiver..." required></textarea>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-primary">Submit Waiver Request</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Unavailable Modal -->
                                        <div class="modal fade" id="unavailableModal<?php echo (int)$rx->iop_med_id; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <form method="post" action="<?php echo base_url(); ?>app/pharmacy/mark_unavailable">
                                                        <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                                                        <div class="modal-header bg-red">
                                                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                                                            <h4 class="modal-title"><i class="fa fa-ban"></i> Mark Unavailable</h4>
                                                        </div>
                                                        <div class="modal-body">
                                                            <input type="hidden" name="iop_med_id" value="<?php echo (int)$rx->iop_med_id; ?>">
                                                            <input type="hidden" name="return_url" value="<?php echo htmlspecialchars($return_url); ?>">
                                                            <p class="text-danger"><i class="fa fa-warning"></i> This medication will be marked as <strong>UNAVAILABLE</strong> and will NOT appear on the patient's bill.</p>
                                                            <div class="form-group">
                                                                <label>Reason / Notes</label>
                                                                <textarea class="form-control" name="notes" rows="3" placeholder="e.g. Out of stock, alternative prescribed..."></textarea>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-danger">Confirm Unavailable</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
										<?php endif; ?>
									<?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

            </section>
        </aside>
    </div>

    <script src="<?php echo base_url();?>public/js/jquery.min.js"></script>
    <script src="<?php echo base_url();?>public/js/bootstrap.min.js" type="text/javascript"></script>
    <script src="<?php echo base_url();?>public/js/AdminLTE/app.js" type="text/javascript"></script>

    <!-- Phase 6B toast container -->
    <div class="pd-toast-container" id="pdToastContainer"></div>

    <script>
    (function($) {
        'use strict';

        var AJAX_URL = '<?php echo base_url(); ?>app/pharmacy/dispense_ajax';

        /* ── Toast helper ── */
        function pdToast(msg, type) {
            type = type || 'success';
            var t = $('<div class="pd-toast ' + type + '">' + $('<span>').text(msg).html() + '</div>');
            $('#pdToastContainer').append(t);
            setTimeout(function() { t.fadeOut(400, function() { $(this).remove(); }); }, 4000);
        }

        /* ── AJAX Dispense/Partial form submit ── */
        $(document).on('submit', '.ajax-dispense-form', function(e) {
            e.preventDefault();
            var $form   = $(this);
            var $btn    = $form.find('.ajax-submit-btn');
            var $err    = $form.find('.ajax-modal-error');
            var $modal  = $form.closest('.modal');
            var medId   = $form.find('[name="iop_med_id"]').val();
            var status  = $form.find('[name="status"]').val();

            $err.hide();
            $btn.prop('disabled', true)
                .find('.fa:not(.fa-spin-inline)').hide();
            $btn.find('.fa-spin-inline').show();

            /* If manual batch input is visible (no batch stock), copy into the select value */
            var $manualBatch = $form.find('.batch-manual');
            if ($manualBatch.length && $manualBatch.val()) {
                $form.find('.batch-select').val($manualBatch.val());
            }

            $.post(AJAX_URL, $form.serialize())
                .done(function(resp) {
                    if (resp.ok) {
                        $modal.modal('hide');
                        pdToast(resp.message, 'success');

                        /* Update RX card status label + progress bar inline */
                        var $card = $('#rxItem' + medId);
                        if ($card.length && resp.status_label) {
                            var sLower = resp.status_label.toLowerCase();
                            /* Update border/class */
                            $card.removeClass('status-pending status-partial status-dispensed status-external status-unavailable')
                                 .addClass('status-' + sLower)
                                 .addClass('just-dispensed');
                            setTimeout(function() { $card.removeClass('just-dispensed'); }, 1100);

                            /* Update progress bar */
                            var total   = parseFloat($card.find('.progress-bar').text().split('/')[1]) || 0;
                            var disp    = resp.dispensed_qty !== null ? resp.dispensed_qty : 0;
                            var pct     = total > 0 ? Math.min(100, Math.round(disp / total * 100)) : 0;
                            var barClass = pct >= 100 ? 'progress-bar-success' : (pct > 0 ? 'progress-bar-warning' : 'progress-bar-danger');
                            $card.find('.progress-bar')
                                 .css('width', pct + '%')
                                 .attr('class', 'progress-bar ' + barClass)
                                 .text(disp + ' / ' + total);

                            /* Update stock display */
                            if (resp.current_stock !== null) {
                                $card.find('.lbl-stock').text(Math.floor(resp.current_stock));
                                $card.find('.rx-progress .text-success, .rx-progress .stock-warning')
                                     .text(Math.floor(resp.current_stock));
                            }

                            /* If fully dispensed — collapse action buttons */
                            if (sLower === 'dispensed') {
                                $card.find('.rx-actions').html(
                                    '<span class="label label-success"><i class="fa fa-check-circle"></i> Dispensed</span>'
                                );
                            }
                        }
                    } else {
                        $err.text(resp.message || 'An error occurred').show();
                        pdToast(resp.message || 'Error', 'error');
                    }
                })
                .fail(function() {
                    $err.text('Server error. Please try again.').show();
                    pdToast('Server error — please try again.', 'error');
                })
                .always(function() {
                    $btn.prop('disabled', false)
                        .find('.fa:not(.fa-spin-inline)').show();
                    $btn.find('.fa-spin-inline').hide();
                });
        });

        function pdMarkVerified(medId) {
            medId = parseInt(medId, 10) || 0;
            if (!medId) { return; }
            var $card = $('#rxItem' + medId);
            if (!$card.length) { return; }
            $card.attr('data-verified', '1');
            $card.find('.alert-warning:contains("Verification Required")').remove();
            var $verifyBtn = $card.find('button[data-target="#verifyModal' + medId + '"]');
            if ($verifyBtn.length) {
                $verifyBtn.replaceWith('<span class="label label-success"><i class="fa fa-check"></i> Verified</span>');
            }
        }

        var DRUG_SEARCH_URL = '<?php echo base_url(); ?>app/pharmacy/drug_search_json';
        var pdDrugSearchTimer = null;

        function pdRenderDrugResults($modal, items) {
            var $res = $modal.find('.substitute-drug-results');
            if (!$res.length) { return; }
            if (!items || !items.length) {
                $res.html('<div class="text-muted" style="padding:6px 0;">No results</div>');
                return;
            }
            var html = '<div class="list-group" style="max-height:220px; overflow:auto;">';
            $.each(items, function(_, it) {
                if (!it || !it.id) { return; }
                var disabled = it.in_stock ? '' : ' disabled';
                var stockTxt = it.in_stock ? ('<span class="text-success">Stock: ' + it.stock + '</span>') : ('<span class="text-danger">OUT OF STOCK</span>');
                html += '<a href="#" class="list-group-item' + disabled + '" data-drug-id="' + it.id + '" data-drug-label="' + $('<span>').text(it.label).html() + '" data-in-stock="' + (it.in_stock ? '1' : '0') + '">' +
                    '<div style="font-weight:600;">' + $('<span>').text(it.label).html() + '</div>' +
                    '<div style="font-size:12px;">' + stockTxt + '</div>' +
                '</a>';
            });
            html += '</div>';
            $res.html(html);
        }

        $(document).on('shown.bs.modal', 'div.modal[id^="substituteModal"]', function() {
            var $modal = $(this);
            $modal.find('.substitute-drug-search').val('');
            $modal.find('.substitute-drug-results').empty();
            $modal.find('.substitute-drug-selected').hide().empty();
        });

        $(document).on('keyup', '.substitute-drug-search', function() {
            var $search = $(this);
            var $modal = $search.closest('.modal');
            var term = $.trim($search.val() || '');
            if (pdDrugSearchTimer) { clearTimeout(pdDrugSearchTimer); }
            pdDrugSearchTimer = setTimeout(function() {
                if (term.length < 2) {
                    $modal.find('.substitute-drug-results').empty();
                    return;
                }
                $.getJSON(DRUG_SEARCH_URL, { term: term }, function(items) {
                    pdRenderDrugResults($modal, items);
                }).fail(function() {
                    $modal.find('.substitute-drug-results').html('<div class="text-danger" style="padding:6px 0;">Search failed</div>');
                });
            }, 250);
        });

        $(document).on('click', '.substitute-drug-results a.list-group-item', function(e) {
            e.preventDefault();
            var $a = $(this);
            if ($a.hasClass('disabled') || $a.attr('data-in-stock') === '0') {
                return;
            }
            var drugId = parseInt($a.attr('data-drug-id'), 10) || 0;
            var label = String($a.attr('data-drug-label') || '');
            var $modal = $a.closest('.modal');
            if (!drugId) { return; }
            $modal.find('input[name="substitute_drug_id"]').val(drugId);
            $modal.find('.substitute-drug-search').val(label);
            $modal.find('.substitute-drug-selected').html('<i class="fa fa-check"></i> Selected: <strong>' + label + '</strong> (ID: ' + drugId + ')').show();
            $modal.find('.substitute-drug-results').empty();
        });

        $(document).on('submit', '.ajax-verify-one-form', function(e) {
            e.preventDefault();
            var $form = $(this);
            if ($form.data('submitting')) { return false; }
            $form.data('submitting', true);
            var $modal = $form.closest('.modal');
            var action = String($form.attr('action') || '');
            var m = action.match(/verify_prescription\/(\d+)/);
            var medId = (m && m[1]) ? parseInt(m[1], 10) : 0;
            var $btn = $form.find('button[type="submit"]');
            $btn.prop('disabled', true);

            $.ajax({
                url: action,
                type: 'POST',
                data: $form.serialize(),
                dataType: 'json',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .done(function(resp) {
                    if (resp && resp.success) {
                        pdMarkVerified(resp.iop_med_id || medId);
                        $('#btnFinalizeForBilling').prop('disabled', false);
                        $modal.modal('hide');
                        pdToast(resp.message || 'Verified.', 'success');
                    } else {
                        pdToast((resp && (resp.error || resp.message)) ? (resp.error || resp.message) : 'Verification failed.', 'error');
                    }
                })
                .fail(function() {
                    pdToast('Verification failed.', 'error');
                })
                .always(function() {
                    $form.data('submitting', false);
                    $btn.prop('disabled', false);
                });
        });

        $(document).on('submit', '.ajax-bulk-verify-form', function(e) {
            e.preventDefault();
            var $form = $(this);
            var $btn = $form.find('button[type="submit"]');
            $btn.prop('disabled', true);
            $.post($form.attr('action'), $form.serialize())
                .done(function(resp) {
                    if (resp && resp.verified > 0) {
                        if (resp.verified_ids && resp.verified_ids.length) {
                            $.each(resp.verified_ids, function(_, id) {
                                pdMarkVerified(id);
                            });
                        }
                        pdToast('Verified ' + resp.verified + ' prescription(s).', resp.success ? 'success' : 'warning');
                        $('#btnFinalizeForBilling').prop('disabled', false);
                    } else {
                        pdToast('No prescriptions were verified.', 'warning');
                    }
                })
                .fail(function() {
                    pdToast('Bulk verification failed.', 'error');
                })
                .always(function() {
                    $btn.prop('disabled', false);
                });
        });

        /* ── Batch dropdown loader for dispense/partial modals ── */
        var BATCH_URL = '<?php echo base_url(); ?>app/pharmacy/batches_json';

        $(document).on('show.bs.modal', '.modal[data-drug-id]', function() {
            var $modal   = $(this);
            var drugId   = parseInt($modal.attr('data-drug-id'), 10);
            var $sel     = $modal.find('.batch-select');
            var $expiry  = $modal.find('.batch-expiry');

            if (!drugId) {
                $sel.html('<option value="">No batch tracking</option>');
                return;
            }

            $sel.html('<option value=""><i class="fa fa-spinner"></i> Loading\u2026</option>').prop('disabled', true);
            $expiry.val('');

            $.getJSON(BATCH_URL, { drug_id: drugId }, function(resp) {
                $sel.prop('disabled', false);
                if (!resp.ok || resp.batches.length === 0) {
                    $sel.html('<option value="">No batch stock \u2014 enter manually</option>');
                    $sel.after(
                        $sel.parent().find('.batch-manual').length ? '' :
                        '<input type="text" class="form-control batch-manual" name="batch_no_manual" placeholder="Enter batch no. manually" style="margin-top:6px;">'
                    );
                    return;
                }
                var opts = '<option value="">-- Select batch --</option>';
                $.each(resp.batches, function(i, b) {
                    var expLabel = b.expiry_date ? ' | Exp: ' + b.expiry_date : '';
                    var warn = '';
                    if (b.expiry_date) {
                        var daysLeft = Math.floor((new Date(b.expiry_date) - new Date()) / 86400000);
                        if (daysLeft < 0)       warn = ' \u26a0\ufe0f EXPIRED';
                        else if (daysLeft < 90) warn = ' \u26a0\ufe0f Exp soon';
                    }
                    opts += '<option value="' + $('<span>').text(b.batch_number).html() + '" data-expiry="' + (b.expiry_date || '') + '">'
                          + $('<span>').text(b.batch_number).html()
                          + ' (Qty: ' + b.quantity + expLabel + warn + ')'
                          + '</option>';
                });
                $sel.html(opts);
                /* Auto-select first (FEFO) batch and fill expiry */
                if (resp.batches[0]) {
                    $sel.val(resp.batches[0].batch_number);
                    $expiry.val(resp.batches[0].expiry_date || '\u2014');
                }
            }).fail(function() {
                $sel.prop('disabled', false)
                    .html('<option value="">Could not load batches</option>');
            });
        });

        /* Update expiry field when batch selection changes */
        $(document).on('change', '.batch-select', function() {
            var expiry = $(this).find('option:selected').data('expiry') || '';
            $(this).closest('.modal-body').find('.batch-expiry').val(expiry || '\u2014');
        });

    })(jQuery);
    </script>
</body>
</html>

