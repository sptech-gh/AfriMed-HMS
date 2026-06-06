<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<title><?php echo isset($page_title) ? $page_title : 'Billing Audit Log'; ?> - HMS</title>
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
			<h1><i class="fa fa-history"></i> Billing Audit Log</h1>
			<ol class="breadcrumb">
				<li><a href="<?php echo base_url(); ?>app/dashboard"><i class="fa fa-dashboard"></i> Dashboard</a></li>
				<li><a href="<?php echo base_url(); ?>app/cashier">Cashier</a></li>
				<li class="active">Audit Log</li>
			</ol>
		</section>

		<section class="content">
			<?php if (isset($message) && $message): ?>
				<?php echo $message; ?>
			<?php endif; ?>

			<!-- Filters -->
			<div class="box box-primary">
				<div class="box-header with-border">
					<h3 class="box-title"><i class="fa fa-filter"></i> Filters</h3>
				</div>
				<div class="box-body">
					<form method="get" class="form-inline">
						<div class="form-group">
							<label>Action:</label>
							<select name="action" class="form-control">
								<option value="">All Actions</option>
								<option value="PAYMENT" <?php echo (isset($filters['action_type']) && $filters['action_type'] === 'PAYMENT') ? 'selected' : ''; ?>>Payment</option>
								<option value="INVOICE" <?php echo (isset($filters['action_type']) && $filters['action_type'] === 'INVOICE') ? 'selected' : ''; ?>>Invoice</option>
								<option value="VOID" <?php echo (isset($filters['action_type']) && $filters['action_type'] === 'VOID') ? 'selected' : ''; ?>>Void</option>
								<option value="AUTO_FIX" <?php echo (isset($filters['action_type']) && $filters['action_type'] === 'AUTO_FIX') ? 'selected' : ''; ?>>Auto Fix</option>
							</select>
						</div>
						<div class="form-group">
							<label>Invoice:</label>
							<input type="text" name="invoice" class="form-control" placeholder="Invoice #" value="<?php echo isset($filters['invoice_no']) ? htmlspecialchars($filters['invoice_no']) : ''; ?>">
						</div>
						<div class="form-group">
							<label>From:</label>
							<input type="date" name="from" class="form-control" value="<?php echo isset($filters['date_from']) ? $filters['date_from'] : ''; ?>">
						</div>
						<div class="form-group">
							<label>To:</label>
							<input type="date" name="to" class="form-control" value="<?php echo isset($filters['date_to']) ? $filters['date_to'] : ''; ?>">
						</div>
						<button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> Filter</button>
						<a href="<?php echo base_url(); ?>app/cashier/audit_log" class="btn btn-default">Reset</a>
					</form>
				</div>
			</div>

			<!-- Audit Log Table -->
			<div class="box box-info">
				<div class="box-header with-border">
					<h3 class="box-title"><i class="fa fa-list"></i> Audit Entries</h3>
					<span class="badge bg-blue pull-right"><?php echo count($entries); ?> entries</span>
				</div>
				<div class="box-body table-responsive">
					<table class="table table-bordered table-striped table-hover">
						<thead>
							<tr>
								<th width="140">Date/Time</th>
								<th width="100">Action</th>
								<th width="80">Entity</th>
								<th width="100">Invoice</th>
								<th>Description</th>
								<th width="100">Amount</th>
								<th width="120">User</th>
								<th width="100">IP</th>
							</tr>
						</thead>
						<tbody>
							<?php if (empty($entries)): ?>
								<tr><td colspan="8" class="text-center text-muted">No audit entries found</td></tr>
							<?php else: ?>
								<?php foreach ($entries as $e): ?>
									<tr>
										<td><?php echo date('Y-m-d H:i', strtotime($e->performed_at)); ?></td>
										<td>
											<?php
											$badge = 'default';
											if ($e->action_type === 'PAYMENT') $badge = 'success';
											elseif ($e->action_type === 'INVOICE') $badge = 'info';
											elseif ($e->action_type === 'VOID') $badge = 'danger';
											elseif ($e->action_type === 'AUTO_FIX') $badge = 'warning';
											?>
											<span class="label label-<?php echo $badge; ?>"><?php echo $e->action_type; ?></span>
										</td>
										<td><?php echo $e->entity_type ?: '-'; ?></td>
										<td><?php echo $e->invoice_no ?: '-'; ?></td>
										<td>
											<?php echo htmlspecialchars($e->description ?: ''); ?>
											<?php if ($e->old_value || $e->new_value): ?>
												<br><small class="text-muted">
													<?php if ($e->old_value): ?>Old: <?php echo htmlspecialchars(substr($e->old_value, 0, 50)); ?><?php endif; ?>
													<?php if ($e->new_value): ?> &rarr; New: <?php echo htmlspecialchars(substr($e->new_value, 0, 50)); ?><?php endif; ?>
												</small>
											<?php endif; ?>
										</td>
										<td class="text-right"><?php echo $e->amount ? number_format($e->amount, 2) : '-'; ?></td>
										<td><?php echo ($e->firstname ? $e->firstname . ' ' . $e->lastname : $e->performed_by); ?></td>
										<td><small><?php echo $e->ip_address; ?></small></td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
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
