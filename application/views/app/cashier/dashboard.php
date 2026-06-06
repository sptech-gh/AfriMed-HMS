<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<title><?php echo isset($page_title) ? $page_title : 'Cashier Dashboard'; ?> - HMS</title>
	<meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>
	<link href="<?php echo base_url(); ?>bootstrap/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
	<link href="<?php echo base_url(); ?>bootstrap/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
	<link href="<?php echo base_url(); ?>dist/css/AdminLTE.min.css" rel="stylesheet" type="text/css" />
	<link href="<?php echo base_url(); ?>dist/css/skins/skin-blue.min.css" rel="stylesheet" type="text/css" />
	<style>
		.quick-action-btn { margin-bottom: 10px; width: 100%; }
		.stat-box { border-left: 4px solid; padding: 15px; margin-bottom: 15px; background: #fff; }
		.stat-box.green { border-color: #00a65a; }
		.stat-box.blue { border-color: #3c8dbc; }
		.stat-box.yellow { border-color: #f39c12; }
		.stat-box.red { border-color: #dd4b39; }
		.stat-box h3 { margin: 0 0 5px 0; font-size: 28px; }
		.stat-box p { margin: 0; color: #666; }
	</style>
</head>
<body class="skin-blue">
<?php require_once(APPPATH . 'views/include/header.php'); ?>

<div class="wrapper row-offcanvas row-offcanvas-left">
	<?php require_once(APPPATH . 'views/include/sidebar.php'); ?>

	<aside class="right-side">
		<section class="content-header">
			<h1><i class="fa fa-dashboard"></i> Cashier Dashboard</h1>
			<ol class="breadcrumb">
				<li><a href="<?php echo base_url(); ?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
				<li class="active">Cashier</li>
			</ol>
		</section>

		<section class="content">
			<?php if (isset($message) && $message): ?>
				<?php echo $message; ?>
			<?php endif; ?>

			<!-- Quick Actions -->
			<div class="row">
				<div class="col-md-3">
					<a href="<?php echo base_url(); ?>app/cashier/billing_queue" class="btn btn-warning btn-lg quick-action-btn">
						<i class="fa fa-clock-o"></i> Billing Queue
						<?php if (isset($queue_summary['pending_count']) && $queue_summary['pending_count'] > 0): ?>
							<span class="badge"><?php echo $queue_summary['pending_count']; ?></span>
						<?php endif; ?>
					</a>
				</div>
				<div class="col-md-3">
					<a href="<?php echo base_url(); ?>app/billing/smart_billing" class="btn btn-success btn-lg quick-action-btn">
						<i class="fa fa-plus-circle"></i> Create Bill
					</a>
				</div>
				<div class="col-md-3">
					<a href="<?php echo base_url(); ?>app/cashier/payments" class="btn btn-primary btn-lg quick-action-btn">
						<i class="fa fa-credit-card"></i> Collect Payment
					</a>
				</div>
				<div class="col-md-3">
					<a href="<?php echo base_url(); ?>app/billing/searchPatient" class="btn btn-info btn-lg quick-action-btn">
						<i class="fa fa-search"></i> Search Bills
					</a>
				</div>
			</div>

			<!-- Today's Summary -->
			<div class="row">
				<div class="col-md-3">
					<div class="stat-box green">
						<h3>GHS <?php echo isset($stats['payments']['total']) ? number_format($stats['payments']['total'], 2) : '0.00'; ?></h3>
						<p><i class="fa fa-money"></i> Today's Collections</p>
					</div>
				</div>
				<div class="col-md-3">
					<div class="stat-box blue">
						<h3><?php echo isset($stats['invoices']['count']) ? number_format($stats['invoices']['count']) : '0'; ?></h3>
						<p><i class="fa fa-file-text-o"></i> Invoices Created</p>
					</div>
				</div>
				<div class="col-md-3">
					<div class="stat-box yellow">
						<h3>GHS <?php echo isset($stats['outstanding']) ? number_format($stats['outstanding'], 2) : '0.00'; ?></h3>
						<p><i class="fa fa-clock-o"></i> Outstanding Balance</p>
					</div>
				</div>
				<div class="col-md-3">
					<div class="stat-box red">
						<h3><?php echo isset($payment_summary['unpaid_count']) ? number_format($payment_summary['unpaid_count']) : '0'; ?></h3>
						<p><i class="fa fa-exclamation-circle"></i> Unpaid Invoices</p>
					</div>
				</div>
			</div>

			<div class="row">
				<!-- Unpaid Invoices -->
				<div class="col-md-6">
					<div class="box box-warning">
						<div class="box-header with-border">
							<h3 class="box-title"><i class="fa fa-exclamation-triangle"></i> Pending Payments</h3>
							<a href="<?php echo base_url(); ?>app/cashier/payments" class="btn btn-xs btn-default pull-right">View All</a>
						</div>
						<div class="box-body table-responsive no-padding">
							<table class="table table-hover">
								<thead>
									<tr>
										<th>Invoice</th>
										<th>Patient</th>
										<th class="text-right">Amount</th>
										<th class="text-right">Balance</th>
										<th></th>
									</tr>
								</thead>
								<tbody>
									<?php if (empty($unpaid_invoices)): ?>
										<tr><td colspan="5" class="text-center text-muted">No pending payments</td></tr>
									<?php else: ?>
										<?php foreach ($unpaid_invoices as $inv): ?>
											<tr>
												<td><a href="<?php echo base_url(); ?>app/cashier/invoice/<?php echo $inv->invoice_no; ?>"><?php echo $inv->invoice_no; ?></a></td>
												<td><?php echo (isset($inv->firstname) ? $inv->firstname . ' ' . $inv->lastname : $inv->patient_no); ?></td>
												<td class="text-right">GHS <?php echo number_format($inv->total_amount, 2); ?></td>
												<td class="text-right text-danger"><strong>GHS <?php echo number_format($inv->balance, 2); ?></strong></td>
												<td>
													<a href="<?php echo base_url(); ?>app/cashier/invoice/<?php echo $inv->invoice_no; ?>" class="btn btn-xs btn-success">
														<i class="fa fa-credit-card"></i> Pay
													</a>
												</td>
											</tr>
										<?php endforeach; ?>
									<?php endif; ?>
								</tbody>
							</table>
						</div>
					</div>
				</div>

				<!-- Today's Payments -->
				<div class="col-md-6">
					<div class="box box-success">
						<div class="box-header with-border">
							<h3 class="box-title"><i class="fa fa-check-circle"></i> Today's Payments</h3>
							<a href="<?php echo base_url(); ?>app/billing_history" class="btn btn-xs btn-default pull-right">View All</a>
						</div>
						<div class="box-body table-responsive no-padding">
							<table class="table table-hover">
								<thead>
									<tr>
										<th>Receipt</th>
										<th>Patient</th>
										<th>Method</th>
										<th class="text-right">Amount</th>
									</tr>
								</thead>
								<tbody>
									<?php if (empty($today_payments)): ?>
										<tr><td colspan="4" class="text-center text-muted">No payments today</td></tr>
									<?php else: ?>
										<?php foreach ($today_payments as $pay): ?>
											<tr>
												<td><?php echo $pay->receipt_no; ?></td>
												<td><?php echo (isset($pay->firstname) ? $pay->firstname . ' ' . $pay->lastname : $pay->patient_no); ?></td>
												<td><span class="label label-info"><?php echo isset($pay->payment_type) ? $pay->payment_type : 'CASH'; ?></span></td>
												<td class="text-right text-success"><strong>GHS <?php echo number_format($pay->amountPaid, 2); ?></strong></td>
											</tr>
										<?php endforeach; ?>
									<?php endif; ?>
								</tbody>
							</table>
						</div>
					</div>
				</div>
			</div>

			<!-- Payment Methods Breakdown -->
			<?php if (isset($stats['by_payment_method']) && !empty($stats['by_payment_method'])): ?>
			<div class="row">
				<div class="col-md-12">
					<div class="box box-info">
						<div class="box-header with-border">
							<h3 class="box-title"><i class="fa fa-pie-chart"></i> Today's Collections by Payment Method</h3>
						</div>
						<div class="box-body">
							<div class="row">
								<?php foreach ($stats['by_payment_method'] as $method => $data): ?>
									<div class="col-md-2 col-sm-4 col-xs-6 text-center">
										<div class="description-block border-right">
											<h5 class="description-header">GHS <?php echo number_format($data['total'], 2); ?></h5>
											<span class="description-text"><?php echo $method; ?></span>
											<br><small class="text-muted"><?php echo $data['count']; ?> payments</small>
										</div>
									</div>
								<?php endforeach; ?>
							</div>
						</div>
					</div>
				</div>
			</div>
			<?php endif; ?>

			<!-- Reconciliation Summary -->
			<?php if (isset($reconciliation)): ?>
			<div class="row">
				<div class="col-md-12">
					<div class="box box-primary">
						<div class="box-header with-border">
							<h3 class="box-title"><i class="fa fa-balance-scale"></i> Daily Reconciliation</h3>
							<a href="<?php echo base_url(); ?>app/cashier/reconciliation" class="btn btn-xs btn-primary pull-right">Full Report</a>
						</div>
						<div class="box-body">
							<div class="row">
								<div class="col-md-3 text-center">
									<h4>GHS <?php echo number_format($reconciliation['total_billed'], 2); ?></h4>
									<p class="text-muted">Total Billed</p>
								</div>
								<div class="col-md-3 text-center">
									<h4 class="text-success">GHS <?php echo number_format($reconciliation['total_collected'], 2); ?></h4>
									<p class="text-muted">Total Collected</p>
								</div>
								<div class="col-md-3 text-center">
									<h4 class="text-warning">GHS <?php echo number_format($reconciliation['total_outstanding'], 2); ?></h4>
									<p class="text-muted">Outstanding</p>
								</div>
								<div class="col-md-3 text-center">
									<h4><?php echo $reconciliation['invoices_created']; ?></h4>
									<p class="text-muted">Invoices Created</p>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
			<?php endif; ?>

		</section>
	</aside>
</div>

<script src="<?php echo base_url(); ?>bootstrap/js/jquery.min.js"></script>
<script src="<?php echo base_url(); ?>bootstrap/js/bootstrap.min.js"></script>
<script src="<?php echo base_url(); ?>dist/js/app.min.js"></script>
</body>
</html>
