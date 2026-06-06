<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo isset($companyInfo->site_title) ? $companyInfo->site_title : 'HMS'; ?> — Daily Reconciliation</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
    <style>
        .reconciliation-card { border-left: 4px solid #3c8dbc; }
        .reconciliation-card.success { border-left-color: #00a65a; }
        .reconciliation-card.warning { border-left-color: #f39c12; }
        .reconciliation-card.danger { border-left-color: #dd4b39; }
        .stat-value { font-size: 28px; font-weight: 600; }
        .stat-label { color: #777; font-size: 13px; text-transform: uppercase; }
        .match-badge { font-size: 11px; padding: 3px 8px; }
    </style>
</head>
<body class="skin-blue">
    <?php require_once(APPPATH.'views/include/header.php');?>
    <div class="wrapper row-offcanvas row-offcanvas-left">
        <?php require_once(APPPATH.'views/include/sidebar.php');?>
        <aside class="right-side">
            <section class="content-header">
                <h1>Daily Reconciliation <small>Financial integrity check</small></h1>
                <ol class="breadcrumb">
                    <li><a href="<?php echo base_url();?>app/dashboard"><i class="fa fa-dashboard"></i> Dashboard</a></li>
                    <li><a href="<?php echo base_url();?>app/cashier/payments">Cashier</a></li>
                    <li class="active">Reconciliation</li>
                </ol>
            </section>
            <section class="content">
                <?php echo isset($message) ? $message : ''; ?>

                <!-- Date Filter -->
                <div class="box box-primary">
                    <div class="box-body">
                        <form method="get" class="form-inline">
                            <div class="form-group">
                                <label>Date:</label>
                                <input type="date" name="date" class="form-control" value="<?php echo $date; ?>">
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> Check</button>
                            <a href="<?php echo base_url();?>app/cashier/reconciliation" class="btn btn-default">Today</a>
                        </form>
                    </div>
                </div>

                <!-- Summary Dashboard -->
                <div class="row">
                    <div class="col-md-3">
                        <div class="box reconciliation-card">
                            <div class="box-body">
                                <div class="stat-value text-primary"><?php echo isset($dashboard['invoices_today']) ? $dashboard['invoices_today'] : 0; ?></div>
                                <div class="stat-label">Invoices Today</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="box reconciliation-card success">
                            <div class="box-body">
                                <div class="stat-value text-success">GHS <?php echo number_format(isset($dashboard['collections_today']) ? $dashboard['collections_today'] : 0, 2); ?></div>
                                <div class="stat-label">Collections Today</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="box reconciliation-card warning">
                            <div class="box-body">
                                <div class="stat-value text-warning"><?php echo isset($dashboard['unpaid_count']) ? $dashboard['unpaid_count'] : 0; ?></div>
                                <div class="stat-label">Unpaid Invoices</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="box reconciliation-card danger">
                            <div class="box-body">
                                <div class="stat-value text-danger">GHS <?php echo number_format(isset($dashboard['outstanding']) ? $dashboard['outstanding'] : 0, 2); ?></div>
                                <div class="stat-label">Outstanding Balance</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Reconciliation Details -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="box box-info">
                            <div class="box-header with-border">
                                <h3 class="box-title"><i class="fa fa-check-circle"></i> Billing Reconciliation</h3>
                            </div>
                            <div class="box-body">
                                <table class="table table-bordered">
                                    <tr>
                                        <td><strong>Services Created</strong></td>
                                        <td class="text-right"><?php echo isset($reconciliation['services_created']) ? $reconciliation['services_created'] : 0; ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Services Billed</strong></td>
                                        <td class="text-right"><?php echo isset($reconciliation['services_billed']) ? $reconciliation['services_billed'] : 0; ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Services Pending</strong></td>
                                        <td class="text-right">
                                            <?php 
                                            $pending = isset($reconciliation['services_pending']) ? $reconciliation['services_pending'] : 0;
                                            if ($pending > 0) {
                                                echo '<span class="label label-warning">' . $pending . '</span>';
                                            } else {
                                                echo '<span class="label label-success">0</span>';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Invoices Created</strong></td>
                                        <td class="text-right"><?php echo isset($reconciliation['invoices_created']) ? $reconciliation['invoices_created'] : 0; ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="box box-success">
                            <div class="box-header with-border">
                                <h3 class="box-title"><i class="fa fa-money"></i> Financial Summary</h3>
                            </div>
                            <div class="box-body">
                                <table class="table table-bordered">
                                    <tr>
                                        <td><strong>Total Billed</strong></td>
                                        <td class="text-right">GHS <?php echo number_format(isset($reconciliation['total_billed']) ? $reconciliation['total_billed'] : 0, 2); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Total Collected</strong></td>
                                        <td class="text-right text-success">GHS <?php echo number_format(isset($reconciliation['total_collected']) ? $reconciliation['total_collected'] : 0, 2); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Collection Rate</strong></td>
                                        <td class="text-right">
                                            <?php 
                                            $billed = isset($reconciliation['total_billed']) ? (float)$reconciliation['total_billed'] : 0;
                                            $collected = isset($reconciliation['total_collected']) ? (float)$reconciliation['total_collected'] : 0;
                                            $rate = $billed > 0 ? ($collected / $billed) * 100 : 0;
                                            $rateClass = $rate >= 80 ? 'success' : ($rate >= 50 ? 'warning' : 'danger');
                                            ?>
                                            <span class="label label-<?php echo $rateClass; ?>"><?php echo number_format($rate, 1); ?>%</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Total Outstanding</strong></td>
                                        <td class="text-right text-danger">GHS <?php echo number_format(isset($reconciliation['total_outstanding']) ? $reconciliation['total_outstanding'] : 0, 2); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Integrity Check -->
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-shield"></i> Integrity Status</h3>
                    </div>
                    <div class="box-body">
                        <?php
                        $issues = array();
                        $pending = isset($reconciliation['services_pending']) ? (int)$reconciliation['services_pending'] : 0;
                        if ($pending > 0) {
                            $issues[] = array('warning', $pending . ' services pending billing');
                        }
                        $billed = isset($reconciliation['total_billed']) ? (float)$reconciliation['total_billed'] : 0;
                        $collected = isset($reconciliation['total_collected']) ? (float)$reconciliation['total_collected'] : 0;
                        if ($billed > 0 && $collected < $billed * 0.5) {
                            $issues[] = array('warning', 'Collection rate below 50%');
                        }
                        $outstanding = isset($reconciliation['total_outstanding']) ? (float)$reconciliation['total_outstanding'] : 0;
                        if ($outstanding > 10000) {
                            $issues[] = array('danger', 'High outstanding balance: GHS ' . number_format($outstanding, 2));
                        }
                        ?>
                        
                        <?php if (empty($issues)) { ?>
                            <div class="alert alert-success">
                                <i class="fa fa-check-circle"></i> <strong>All Clear!</strong> No reconciliation issues detected for <?php echo $date; ?>.
                            </div>
                        <?php } else { ?>
                            <?php foreach ($issues as $issue) { ?>
                                <div class="alert alert-<?php echo $issue[0]; ?>">
                                    <i class="fa fa-exclamation-triangle"></i> <?php echo $issue[1]; ?>
                                </div>
                            <?php } ?>
                        <?php } ?>
                    </div>
                </div>

				<!-- Lab Billing Governance (Shadow) -->
				<div class="box box-warning">
					<div class="box-header with-border">
						<h3 class="box-title"><i class="fa fa-eye"></i> Lab Billing Governance (Shadow)</h3>
					</div>
					<div class="box-body">
						<?php
						$shadow = isset($reconciliation['lab_governance_shadow']) && is_array($reconciliation['lab_governance_shadow']) ? $reconciliation['lab_governance_shadow'] : array();
						$shadowOpen = isset($shadow['open_total']) ? (int)$shadow['open_total'] : 0;
						$shadowByType = isset($shadow['by_type']) && is_array($shadow['by_type']) ? $shadow['by_type'] : array();
						$shadowRecent = isset($shadow['recent']) && is_array($shadow['recent']) ? $shadow['recent'] : array();
						?>
						<div class="alert alert-<?php echo $shadowOpen > 0 ? 'warning' : 'success'; ?>">
							<i class="fa fa-info-circle"></i>
							<strong>Open shadow issues:</strong> <?php echo $shadowOpen; ?>
							<span class="text-muted">(lab SSOT + disposition telemetry)</span>
						</div>

						<?php if (!empty($shadowByType)) { ?>
							<table class="table table-bordered">
								<thead>
									<tr>
										<th>Issue Type</th>
										<th class="text-right">Open</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ($shadowByType as $t => $c) { ?>
										<tr>
											<td><?php echo htmlspecialchars((string)$t, ENT_QUOTES, 'UTF-8'); ?></td>
											<td class="text-right"><span class="label label-warning"><?php echo (int)$c; ?></span></td>
										</tr>
									<?php } ?>
								</tbody>
							</table>
						<?php } ?>

						<?php if (!empty($shadowRecent)) { ?>
							<h4>Recent shadow entries</h4>
							<table class="table table-striped">
								<thead>
									<tr>
										<th>ID</th>
										<th>Issue</th>
										<th>Ref</th>
										<th>Time</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ($shadowRecent as $r) {
										$rid = isset($r['recon_id']) ? (int)$r['recon_id'] : 0;
										$itype = isset($r['issue_type']) ? (string)$r['issue_type'] : '';
										$ref = isset($r['record_ref']) ? (string)$r['record_ref'] : '';
										$ts = isset($r['created_at']) ? (string)$r['created_at'] : '';
									?>
										<tr>
											<td><?php echo $rid; ?></td>
											<td><?php echo htmlspecialchars($itype, ENT_QUOTES, 'UTF-8'); ?></td>
											<td><?php echo htmlspecialchars($ref, ENT_QUOTES, 'UTF-8'); ?></td>
											<td><?php echo htmlspecialchars($ts, ENT_QUOTES, 'UTF-8'); ?></td>
										</tr>
									<?php } ?>
								</tbody>
							</table>
						<?php } ?>
					</div>
				</div>

				<!-- Lab Governance Invariants (Cross-module) -->
				<div class="box box-info">
					<div class="box-header with-border">
						<h3 class="box-title"><i class="fa fa-shield"></i> Lab Governance Invariants (Cross-module)</h3>
					</div>
					<div class="box-body">
						<?php
						$inv = isset($reconciliation['lab_governance_invariants']) && is_array($reconciliation['lab_governance_invariants']) ? $reconciliation['lab_governance_invariants'] : array();
						$invScanned = isset($inv['scanned']) ? (int)$inv['scanned'] : 0;
						$invViol = isset($inv['violations']) ? (int)$inv['violations'] : 0;
						$invByType = isset($inv['by_type']) && is_array($inv['by_type']) ? $inv['by_type'] : array();
						$invSample = isset($inv['sample']) && is_array($inv['sample']) ? $inv['sample'] : array();
						?>
						<div class="alert alert-<?php echo $invViol > 0 ? 'info' : 'success'; ?>">
							<i class="fa fa-info-circle"></i>
							<strong>Scanned:</strong> <?php echo $invScanned; ?>
							<span class="text-muted">|</span>
							<strong>Violations:</strong> <?php echo $invViol; ?>
						</div>

						<?php if (!empty($invByType)) { ?>
							<table class="table table-bordered">
								<thead>
									<tr>
										<th>Invariant Issue</th>
										<th class="text-right">Count</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ($invByType as $t => $c) { ?>
										<tr>
											<td><?php echo htmlspecialchars((string)$t, ENT_QUOTES, 'UTF-8'); ?></td>
											<td class="text-right"><span class="label label-info"><?php echo (int)$c; ?></span></td>
										</tr>
									<?php } ?>
								</tbody>
							</table>
						<?php } ?>

						<?php if (!empty($invSample)) { ?>
							<h4>Sample violations</h4>
							<table class="table table-striped">
								<thead>
									<tr>
										<th>Issue</th>
										<th>Txn</th>
										<th>Disp</th>
										<th>Invoice</th>
										<th>Pay</th>
										<th class="text-right">Net</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ($invSample as $r) {
										$it = isset($r['issue_type']) ? (string)$r['issue_type'] : '';
										$rr = isset($r['record_ref']) ? (string)$r['record_ref'] : '';
										$ds = isset($r['disp_state']) ? (string)$r['disp_state'] : '';
										$in = isset($r['invoice_no']) ? (string)$r['invoice_no'] : '';
										$ps = isset($r['payment_status']) ? (string)$r['payment_status'] : '';
										$na = isset($r['net_amount']) ? (float)$r['net_amount'] : 0;
									?>
										<tr>
											<td><?php echo htmlspecialchars($it, ENT_QUOTES, 'UTF-8'); ?></td>
											<td><?php echo htmlspecialchars($rr, ENT_QUOTES, 'UTF-8'); ?></td>
											<td><?php echo htmlspecialchars($ds, ENT_QUOTES, 'UTF-8'); ?></td>
											<td><?php echo htmlspecialchars($in, ENT_QUOTES, 'UTF-8'); ?></td>
											<td><?php echo htmlspecialchars($ps, ENT_QUOTES, 'UTF-8'); ?></td>
											<td class="text-right"><?php echo number_format($na, 2); ?></td>
										</tr>
									<?php } ?>
								</tbody>
							</table>
						<?php } ?>
					</div>
				</div>

                <!-- Quick Actions -->
                <div class="box">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-bolt"></i> Quick Actions</h3>
                    </div>
                    <div class="box-body">
                        <a href="<?php echo base_url();?>app/cashier/payments" class="btn btn-primary">
                            <i class="fa fa-money"></i> Collect Payments
                        </a>
                        <a href="<?php echo base_url();?>app/cashier/billing_queue" class="btn btn-info">
                            <i class="fa fa-list"></i> Billing Queue
                        </a>
                        <a href="<?php echo base_url();?>app/cashier/daily_collection?date=<?php echo $date; ?>" class="btn btn-success">
                            <i class="fa fa-file-text"></i> Daily Report
                        </a>
                        <?php if (has_role('admin')) { ?>
                        <a href="<?php echo base_url();?>app/cashier/ledger" class="btn btn-warning">
                            <i class="fa fa-book"></i> Financial Ledger
                        </a>
                        <?php } ?>
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
