<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<title><?php echo isset($page_title) ? $page_title : 'Billing Statistics'; ?> - HMS</title>
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
			<h1><i class="fa fa-bar-chart"></i> Billing Statistics</h1>
			<ol class="breadcrumb">
				<li><a href="<?php echo base_url(); ?>app/dashboard"><i class="fa fa-dashboard"></i> Dashboard</a></li>
				<li><a href="<?php echo base_url(); ?>app/cashier">Cashier</a></li>
				<li class="active">Statistics</li>
			</ol>
		</section>

		<section class="content">
			<?php if (isset($message) && $message): ?>
				<?php echo $message; ?>
			<?php endif; ?>

			<!-- Date Filter -->
			<div class="box box-primary">
				<div class="box-header with-border">
					<h3 class="box-title"><i class="fa fa-calendar"></i> Period</h3>
				</div>
				<div class="box-body">
					<form method="get" class="form-inline">
						<div class="form-group">
							<label>From:</label>
							<input type="date" name="from" class="form-control" value="<?php echo $date_from; ?>">
						</div>
						<div class="form-group">
							<label>To:</label>
							<input type="date" name="to" class="form-control" value="<?php echo $date_to; ?>">
						</div>
						<button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> Update</button>
					</form>
				</div>
			</div>

			<!-- Summary Cards -->
			<div class="row">
				<div class="col-md-3">
					<div class="small-box bg-aqua">
						<div class="inner">
							<h3><?php echo number_format($stats['invoices']['count']); ?></h3>
							<p>Invoices Created</p>
							<h4>GHS <?php echo number_format($stats['invoices']['total'], 2); ?></h4>
						</div>
						<div class="icon"><i class="fa fa-file-text-o"></i></div>
					</div>
				</div>
				<div class="col-md-3">
					<div class="small-box bg-green">
						<div class="inner">
							<h3><?php echo number_format($stats['payments']['count']); ?></h3>
							<p>Payments Received</p>
							<h4>GHS <?php echo number_format($stats['payments']['total'], 2); ?></h4>
						</div>
						<div class="icon"><i class="fa fa-money"></i></div>
					</div>
				</div>
				<div class="col-md-3">
					<div class="small-box bg-yellow">
						<div class="inner">
							<h3>GHS <?php echo number_format($stats['outstanding'], 2); ?></h3>
							<p>Total Outstanding</p>
							<h4>&nbsp;</h4>
						</div>
						<div class="icon"><i class="fa fa-clock-o"></i></div>
					</div>
				</div>
				<div class="col-md-3">
					<div class="small-box bg-purple">
						<div class="inner">
							<?php $rate = $stats['invoices']['total'] > 0 ? ($stats['payments']['total'] / $stats['invoices']['total']) * 100 : 0; ?>
							<h3><?php echo number_format($rate, 1); ?>%</h3>
							<p>Collection Rate</p>
							<h4>&nbsp;</h4>
						</div>
						<div class="icon"><i class="fa fa-percent"></i></div>
					</div>
				</div>
			</div>

			<div class="row">
				<!-- By Payer Type -->
				<div class="col-md-6">
					<div class="box box-info">
						<div class="box-header with-border">
							<h3 class="box-title"><i class="fa fa-users"></i> By Payer Type</h3>
						</div>
						<div class="box-body">
							<?php if (empty($stats['by_payer_type'])): ?>
								<p class="text-muted text-center">No data available</p>
							<?php else: ?>
								<table class="table table-bordered">
									<thead>
										<tr>
											<th>Payer Type</th>
											<th class="text-right">Invoices</th>
											<th class="text-right">Amount</th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ($stats['by_payer_type'] as $type => $data): ?>
											<tr>
												<td>
													<?php
													$badge = 'default';
													if ($type === 'NHIS') $badge = 'success';
													elseif ($type === 'INSURANCE') $badge = 'info';
													elseif ($type === 'CASH') $badge = 'warning';
													?>
													<span class="label label-<?php echo $badge; ?>"><?php echo $type; ?></span>
												</td>
												<td class="text-right"><?php echo number_format($data['count']); ?></td>
												<td class="text-right">GHS <?php echo number_format($data['total'], 2); ?></td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							<?php endif; ?>
						</div>
					</div>
				</div>

				<!-- By Payment Method -->
				<div class="col-md-6">
					<div class="box box-success">
						<div class="box-header with-border">
							<h3 class="box-title"><i class="fa fa-credit-card"></i> By Payment Method</h3>
						</div>
						<div class="box-body">
							<?php if (empty($stats['by_payment_method'])): ?>
								<p class="text-muted text-center">No data available</p>
							<?php else: ?>
								<table class="table table-bordered">
									<thead>
										<tr>
											<th>Method</th>
											<th class="text-right">Payments</th>
											<th class="text-right">Amount</th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ($stats['by_payment_method'] as $method => $data): ?>
											<tr>
												<td>
													<?php
													$icon = 'money';
													if (stripos($method, 'CARD') !== false) $icon = 'credit-card';
													elseif (stripos($method, 'MOMO') !== false || stripos($method, 'MOBILE') !== false) $icon = 'mobile';
													elseif (stripos($method, 'BANK') !== false) $icon = 'university';
													?>
													<i class="fa fa-<?php echo $icon; ?>"></i> <?php echo $method; ?>
												</td>
												<td class="text-right"><?php echo number_format($data['count']); ?></td>
												<td class="text-right">GHS <?php echo number_format($data['total'], 2); ?></td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							<?php endif; ?>
						</div>
					</div>
				</div>
			</div>

			<!-- Daily Trend -->
			<div class="box box-primary">
				<div class="box-header with-border">
					<h3 class="box-title"><i class="fa fa-line-chart"></i> Last 7 Days Trend</h3>
				</div>
				<div class="box-body">
					<?php if (empty($stats['daily_trend'])): ?>
						<p class="text-muted text-center">No trend data available</p>
					<?php else: ?>
						<table class="table table-bordered table-striped">
							<thead>
								<tr>
									<th>Date</th>
									<th class="text-right">Invoices</th>
									<th class="text-right">Amount Billed</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($stats['daily_trend'] as $day => $data): ?>
									<tr>
										<td><?php echo date('D, M j', strtotime($day)); ?></td>
										<td class="text-right"><?php echo number_format($data['invoices']); ?></td>
										<td class="text-right">GHS <?php echo number_format($data['billed'], 2); ?></td>
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
