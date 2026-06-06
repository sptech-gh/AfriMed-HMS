<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<title>Hebrew Medical Center</title>
	<meta content="width=device-width, initial-scale=1.0" name="viewport">
	<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">

	<link href="<?php echo base_url() ?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
	<link href="<?php echo base_url(); ?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
	<link href="<?php echo base_url(); ?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
	<link href="<?php echo base_url(); ?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
</head>

<body class="skin-blue">
<?php require_once(APPPATH . 'views/include/header.php'); ?>

<div class="wrapper row-offcanvas row-offcanvas-left">
	<?php require_once(APPPATH . 'views/include/sidebar.php'); ?>

	<aside class="right-side">
		<section class="content-header">
			<h1><?php echo isset($title) ? $title : 'Procedure Unit Worklist'; ?></h1>
			<ol class="breadcrumb">
				<li><a href="<?php echo base_url() ?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
				<li class="active">Procedure Unit</li>
			</ol>
		</section>

		<section class="content">
			<?php echo isset($message) ? $message : ''; ?>

			<?php
				$status = isset($filters['status']) ? strtoupper(trim((string)$filters['status'])) : 'PENDING';
				$search = isset($filters['search']) ? (string)$filters['search'] : '';
				$date_from = isset($filters['date_from']) ? (string)$filters['date_from'] : '';
				$date_to = isset($filters['date_to']) ? (string)$filters['date_to'] : '';
				$limit = isset($filters['limit']) ? (int)$filters['limit'] : 200;
				if ($limit <= 0) { $limit = 200; }
			?>

			<div class="box">
				<div class="box-body">
					<form method="get" action="<?php echo base_url('app/procedure_unit'); ?>" class="form-inline">
						<div class="form-group" style="margin-right:8px;">
							<label>Status</label>
							<select name="status" class="form-control" style="margin-left:6px;">
								<option value="PENDING" <?php echo ($status === 'PENDING') ? 'selected' : ''; ?>>Pending</option>
								<option value="COMPLETED" <?php echo ($status === 'COMPLETED') ? 'selected' : ''; ?>>Completed</option>
								<option value="CANCELLED" <?php echo ($status === 'CANCELLED') ? 'selected' : ''; ?>>Cancelled</option>
								<option value="ALL" <?php echo ($status === 'ALL' || $status === '') ? 'selected' : ''; ?>>All</option>
							</select>
						</div>

						<div class="form-group" style="margin-right:8px;">
							<label>From</label>
							<input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($date_from, ENT_QUOTES, 'UTF-8'); ?>" style="margin-left:6px;">
						</div>

						<div class="form-group" style="margin-right:8px;">
							<label>To</label>
							<input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($date_to, ENT_QUOTES, 'UTF-8'); ?>" style="margin-left:6px;">
						</div>

						<div class="form-group" style="margin-right:8px;">
							<label>Search</label>
							<input type="text" name="search" class="form-control" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Patient No / Name / Procedure" style="margin-left:6px; width:280px;">
						</div>

						<div class="form-group" style="margin-right:8px;">
							<label>Limit</label>
							<input type="number" min="1" max="1000" name="limit" class="form-control" value="<?php echo (int)$limit; ?>" style="margin-left:6px; width:90px;">
						</div>

						<button type="submit" class="btn btn-primary"><i class="fa fa-filter"></i> Filter</button>
					</form>
				</div>
			</div>

			<div class="box">
				<div class="box-body table-responsive">
					<table class="table table-hover table-striped">
						<thead>
							<tr>
								<th>Patient</th>
								<th>Procedure</th>
								<th>Qty</th>
								<th>Status</th>
								<th>Payment</th>
								<th>Requested</th>
								<th>Action</th>
							</tr>
						</thead>
						<tbody>
							<?php if (!isset($items) || empty($items)): ?>
								<tr><td colspan="7">No procedure requests</td></tr>
							<?php else: ?>
								<?php foreach ($items as $it): ?>
									<?php
										$patient = (isset($it['patient_no']) ? $it['patient_no'] : '') . ' - ' . (isset($it['patient_name']) ? $it['patient_name'] : '');
										$wf = isset($it['status']) ? strtoupper(trim((string)$it['status'])) : '';
										$can = isset($it['can_proceed']) ? ((int)$it['can_proceed'] === 1 || $it['can_proceed'] === true) : false;
										$reason = isset($it['blocked_reason']) ? (string)$it['blocked_reason'] : '';
										$payer = isset($it['payer_type']) ? strtoupper((string)$it['payer_type']) : '';
										$pay = isset($it['ssot_payment_status']) ? strtoupper((string)$it['ssot_payment_status']) : '';

										$wfBadge = '<span class="label label-default">' . htmlspecialchars($wf, ENT_QUOTES, 'UTF-8') . '</span>';
										if ($wf === 'REQUESTED' || $wf === 'ORDERED' || $wf === 'PENDING') { $wfBadge = '<span class="label label-warning">PENDING</span>'; }
										if ($wf === 'PERFORMED' || $wf === 'COMPLETED') { $wfBadge = '<span class="label label-success">PERFORMED</span>'; }
										if ($wf === 'CANCELLED') { $wfBadge = '<span class="label label-danger">CANCELLED</span>'; }

										$payBadge = '<span class="label label-default">UNBILLED</span>';
										if ($reason === 'NO_SSOT') {
											$payBadge = '<span class="label label-danger">NO BILL</span>';
										} else if ($reason === 'ZERO_PRICE') {
											$payBadge = '<span class="label label-danger">NO PRICE</span>';
										} else if ($payer === 'NHIS' || $pay === 'NHIS') {
											$payBadge = '<span class="label label-primary">NHIS</span>';
										} else if ($pay === 'PAID') {
											$payBadge = '<span class="label label-success">PAID</span>';
										} else if ($pay === 'PARTIAL') {
											$payBadge = '<span class="label label-warning">PARTIAL</span>';
										} else if ($can) {
											$payBadge = '<span class="label label-success">CLEARED</span>';
										} else {
											$payBadge = '<span class="label label-warning">PAYMENT REQUIRED</span>';
										}

										$openUrl = '#';
										if (isset($it['iop_id']) && isset($it['patient_no']) && trim((string)$it['iop_id']) !== '' && trim((string)$it['patient_no']) !== '') {
											$openUrl = base_url('app/opd/view/' . url_safe_id($it['iop_id']) . '/' . urlencode((string)$it['patient_no']));
										}
									?>
									<tr>
										<td><?php echo htmlspecialchars(trim((string)$patient), ENT_QUOTES, 'UTF-8'); ?></td>
										<td><?php echo htmlspecialchars((string)$it['procedure_name'], ENT_QUOTES, 'UTF-8'); ?></td>
										<td><?php echo htmlspecialchars((string)$it['qty'], ENT_QUOTES, 'UTF-8'); ?></td>
										<td><?php echo $wfBadge; ?></td>
										<td><?php echo $payBadge; ?></td>
										<td><?php echo htmlspecialchars((string)$it['requested_at'], ENT_QUOTES, 'UTF-8'); ?></td>
										<td>
											<a class="btn btn-xs btn-default" href="<?php echo $openUrl; ?>" target="_blank">Open Visit</a>

											<?php if ($wf !== 'CANCELLED' && $wf !== 'PERFORMED' && $wf !== 'COMPLETED'): ?>
												<form method="post" action="<?php echo base_url('app/procedure_unit/perform'); ?>" style="display:inline;" onsubmit="return confirm('Mark this procedure as performed?');">
													<input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
													<input type="hidden" name="request_id" value="<?php echo (int)$it['request_id']; ?>">
													<button type="submit" class="btn btn-xs btn-success" <?php echo !$can ? 'disabled="disabled"' : ''; ?> title="<?php echo htmlspecialchars($reason, ENT_QUOTES, 'UTF-8'); ?>">Perform</button>
												</form>

												<form method="post" action="<?php echo base_url('app/procedure_unit/cancel'); ?>" style="display:inline;" onsubmit="var r = prompt('Cancel reason (required):'); if (r === null) return false; r = (r || '').trim(); if (!r) { alert('Cancel reason is required.'); return false; } this.querySelector('input[name=reason]').value = r; return confirm('Cancel this procedure request?');">
													<input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
													<input type="hidden" name="request_id" value="<?php echo (int)$it['request_id']; ?>">
													<input type="hidden" name="reason" value="">
													<button type="submit" class="btn btn-xs btn-danger">Cancel</button>
												</form>
											<?php else: ?>
												<button class="btn btn-xs btn-default" disabled="disabled">No actions</button>
											<?php endif; ?>
										</td>
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

<script src="<?php echo base_url(); ?>public/js/jquery.min.js" type="text/javascript"></script>
<script src="<?php echo base_url(); ?>public/js/bootstrap.min.js" type="text/javascript"></script>
<script src="<?php echo base_url(); ?>public/js/AdminLTE/app.js" type="text/javascript"></script>
</body>
</html>
