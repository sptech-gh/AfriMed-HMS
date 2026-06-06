<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<title><?php echo isset($page_title) ? $page_title : 'Billing Discrepancies'; ?> - HMS</title>
	<meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>
	<link href="<?php echo base_url(); ?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
	<link href="<?php echo base_url(); ?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
	<link href="<?php echo base_url(); ?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
</head>
<body class="skin-blue">
<?php require_once(APPPATH . 'views/include/header.php'); ?>

<div class="wrapper row-offcanvas row-offcanvas-left">
	<?php require_once(APPPATH . 'views/include/sidebar.php'); ?>

	<aside class="right-side">
		<section class="content-header">
			<h1><i class="fa fa-exclamation-triangle"></i> Billing Discrepancies</h1>
			<ol class="breadcrumb">
				<li><a href="<?php echo base_url(); ?>app/dashboard"><i class="fa fa-dashboard"></i> Dashboard</a></li>
				<li><a href="<?php echo base_url(); ?>app/cashier">Cashier</a></li>
				<li class="active">Discrepancies</li>
			</ol>
		</section>

		<section class="content">
			<?php if (isset($message) && $message): ?>
				<?php echo $message; ?>
			<?php endif; ?>

			<!-- Summary -->
			<div class="row">
				<?php
				$critical = 0; $high = 0; $medium = 0; $low = 0;
				foreach ($issues as $i) {
					if ($i['severity'] === 'CRITICAL') $critical++;
					elseif ($i['severity'] === 'HIGH') $high++;
					elseif ($i['severity'] === 'MEDIUM') $medium++;
					else $low++;
				}
				?>
				<div class="col-md-2">
					<div class="small-box" style="background:#8B0000;color:#fff;">
						<div class="inner">
							<h3><?php echo $critical; ?></h3>
							<p>Critical</p>
						</div>
						<div class="icon"><i class="fa fa-bomb"></i></div>
					</div>
				</div>
				<div class="col-md-2">
					<div class="small-box bg-red">
						<div class="inner">
							<h3><?php echo $high; ?></h3>
							<p>High</p>
						</div>
						<div class="icon"><i class="fa fa-exclamation-circle"></i></div>
					</div>
				</div>
				<div class="col-md-2">
					<div class="small-box bg-yellow">
						<div class="inner">
							<h3><?php echo $medium; ?></h3>
							<p>Medium</p>
						</div>
						<div class="icon"><i class="fa fa-warning"></i></div>
					</div>
				</div>
				<div class="col-md-2">
					<div class="small-box bg-blue">
						<div class="inner">
							<h3><?php echo $low; ?></h3>
							<p>Low</p>
						</div>
						<div class="icon"><i class="fa fa-info-circle"></i></div>
					</div>
				</div>
				<div class="col-md-4">
					<div class="small-box bg-green">
						<div class="inner">
							<h3><?php echo count($issues); ?></h3>
							<p>Total Issues</p>
						</div>
						<div class="icon"><i class="fa fa-list"></i></div>
					</div>
				</div>
			</div>

			<!-- Actions -->
			<div class="box box-danger">
				<div class="box-header with-border">
					<h3 class="box-title"><i class="fa fa-wrench"></i> Quick Actions</h3>
				</div>
				<div class="box-body">
					<form method="post" action="<?php echo base_url(); ?>app/cashier/fix_discrepancies" style="display:inline;">
						<input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
						<input type="hidden" name="type" value="HEADER_TOTAL_MISMATCH">
						<button type="submit" class="btn btn-danger" onclick="return confirm('This will sync invoice header totals with line items. This is a CRITICAL fix. Continue?');">
							<i class="fa fa-calculator"></i> Fix Total Mismatches
						</button>
					</form>
					<form method="post" action="<?php echo base_url(); ?>app/cashier/fix_discrepancies" style="display:inline;">
						<input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
						<input type="hidden" name="type" value="OVERPAID">
						<button type="submit" class="btn btn-info" onclick="return confirm('This will adjust invoice totals to match receipts for overpaid invoices. Continue?');">
							<i class="fa fa-money"></i> Fix Overpaid
						</button>
					</form>
					<form method="post" action="<?php echo base_url(); ?>app/cashier/fix_discrepancies" style="display:inline;">
						<input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
						<input type="hidden" name="type" value="PAYMENT_STATUS">
						<button type="submit" class="btn btn-warning" onclick="return confirm('This will auto-fix payment status mismatches. Continue?');">
							<i class="fa fa-magic"></i> Fix Payment Status
						</button>
					</form>
					<form method="post" action="<?php echo base_url(); ?>app/cashier/fix_discrepancies" style="display:inline;">
						<input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
						<button type="submit" class="btn btn-success" onclick="return confirm('This will fix ALL discrepancies automatically. Continue?');">
							<i class="fa fa-check-circle"></i> Fix All
						</button>
					</form>
					<a href="<?php echo base_url(); ?>app/cashier/discrepancies" class="btn btn-default">
						<i class="fa fa-refresh"></i> Refresh
					</a>
				</div>
			</div>

			<!-- Issues Table -->
			<div class="box box-danger">
				<div class="box-header with-border">
					<h3 class="box-title"><i class="fa fa-bug"></i> Detected Issues</h3>
				</div>
				<div class="box-body table-responsive">
					<?php if (empty($issues)): ?>
						<div class="alert alert-success">
							<i class="fa fa-check-circle"></i> No billing discrepancies detected. All systems healthy!
						</div>
					<?php else: ?>
						<table class="table table-bordered table-striped">
							<thead>
								<tr>
									<th width="100">Severity</th>
									<th width="120">Type</th>
									<th width="120">Invoice/Receipt</th>
									<th width="100">Patient</th>
									<th>Message</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($issues as $issue): ?>
									<tr class="<?php echo ($issue['severity'] === 'CRITICAL' || $issue['severity'] === 'HIGH') ? 'danger' : ($issue['severity'] === 'MEDIUM' ? 'warning' : ''); ?>">
										<td>
											<?php
											$badge = 'default';
											if ($issue['severity'] === 'CRITICAL') $badge = 'danger'; // dark red via style
											elseif ($issue['severity'] === 'HIGH') $badge = 'danger';
											elseif ($issue['severity'] === 'MEDIUM') $badge = 'warning';
											else $badge = 'info';
											$style = ($issue['severity'] === 'CRITICAL') ? 'background:#8B0000;' : '';
											?>
											<span class="label label-<?php echo $badge; ?>" style="<?php echo $style; ?>"><?php echo $issue['severity']; ?></span>
										</td>
										<td><code><?php echo $issue['type']; ?></code></td>
										<td>
											<?php if (isset($issue['invoice_no'])): ?>
												<a href="<?php echo base_url(); ?>app/billing_history/view/<?php echo $issue['invoice_no']; ?>">
													<?php echo $issue['invoice_no']; ?>
												</a>
											<?php elseif (isset($issue['receipt_no'])): ?>
												<?php echo $issue['receipt_no']; ?>
											<?php else: ?>
												-
											<?php endif; ?>
										</td>
										<td><?php echo isset($issue['patient_no']) ? $issue['patient_no'] : '-'; ?></td>
										<td><?php echo htmlspecialchars($issue['message']); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</div>
			</div>
		</section>
	</aside>
</div>

<script src="<?php echo base_url(); ?>public/js/jquery.min.js"></script>
<script src="<?php echo base_url(); ?>public/js/bootstrap.min.js"></script>
<script src="<?php echo base_url(); ?>public/js/app.min.js"></script>
</body>
</html>
