<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<title>Hebrew Medical Center</title>
	<meta content="width=device-width, initial-scale=1.0" name="viewport">
	<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
	<link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
	<link href="<?php echo base_url();?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
	<link href="<?php echo base_url();?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
	<link href="<?php echo base_url();?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
	<style>
		.numeric { text-align: right; }
		.negative-balance { background: #f2dede; font-weight: bold; }
	</style>
</head>
<body class="skin-blue">
	<?php require_once(APPPATH.'views/include/header.php');?>
	<div class="wrapper row-offcanvas row-offcanvas-left">
		<?php require_once(APPPATH.'views/include/sidebar.php');?>
		<aside class="right-side">
			<section class="content-header">
				<h1>Patient Intake/Output Balance History</h1>
				<ol class="breadcrumb">
					<li><a href="<?php echo base_url()?>app"><i class="fa fa-home"></i> Home</a></li>
					<li><a href="#">Nurse Module</a></li>
					<li class="active">Intake/Output Balance History</li>
				</ol>
			</section>

			<section class="content">
				<?php echo isset($message) ? $message : ''; ?>
				<div class="row">
					<div class="col-md-3">
						<div class="box box-primary">
							<div class="box-header">
								<h3 class="box-title">Patient</h3>
							</div>
							<div class="box-body">
								<?php
									$patientName = '';
									if (isset($patientInfo) && $patientInfo) {
										if (isset($patientInfo->name)) {
											$patientName = $patientInfo->name;
										} else {
											$patientName = trim((isset($patientInfo->firstname) ? $patientInfo->firstname : '').' '.(isset($patientInfo->lastname) ? $patientInfo->lastname : ''));
										}
									}
								?>
								<table class="table table-bordered">
									<tr><th>IOP ID</th><td><?php echo htmlspecialchars(isset($iop_no) ? $iop_no : ''); ?></td></tr>
									<tr><th>Patient No</th><td><?php echo htmlspecialchars(isset($patient_no) ? $patient_no : ''); ?></td></tr>
									<tr><th>Name</th><td><?php echo htmlspecialchars($patientName); ?></td></tr>
								</table>
							</div>
						</div>
					</div>

					<div class="col-md-9">
						<div class="box box-primary">
							<div class="box-header">
								<h3 class="box-title">Balance Records</h3>
							</div>
							<div class="box-body table-responsive no-padding">
								<table class="table table-hover table-striped">
									<thead>
										<tr>
											<th>Date/Time</th>
											<th class="numeric">Intake Total</th>
											<th class="numeric">Output Total</th>
											<th class="numeric">Balance</th>
											<th>Recorded By</th>
											<th></th>
										</tr>
									</thead>
									<tbody>
										<?php if (isset($io_balance_history) && is_array($io_balance_history) && count($io_balance_history) > 0): ?>
											<?php foreach($io_balance_history as $row): ?>
												<?php $balanceValue = isset($row->balance) ? (float)$row->balance : 0; ?>
												<tr>
													<td><?php echo isset($row->dDateTime) && $row->dDateTime !== '' ? date("d M Y H:i", strtotime($row->dDateTime)) : ''; ?></td>
													<td class="numeric"><?php echo number_format(isset($row->intake_total) ? (float)$row->intake_total : 0, 2); ?></td>
													<td class="numeric"><?php echo number_format(isset($row->output_total) ? (float)$row->output_total : 0, 2); ?></td>
													<td class="numeric <?php echo $balanceValue < 0 ? 'negative-balance' : ''; ?>"><?php echo number_format($balanceValue, 2); ?></td>
													<td><?php echo htmlspecialchars(isset($row->cPreparedBy) ? $row->cPreparedBy : ''); ?></td>
													<td><a href="<?php echo base_url().'app/nurse_module/io_balance_detail/'.(isset($row->type) ? $row->type : '').'/'.(isset($row->record_id) ? $row->record_id : 0).'/'.(isset($row->iop_id) ? $row->iop_id : (isset($iop_no) ? $iop_no : '')).'/'.(isset($patient_no) ? $patient_no : ''); ?>">View</a></td>
												</tr>
											<?php endforeach; ?>
										<?php else: ?>
											<tr><td colspan="6" class="text-muted">No intake/output records found for this patient.</td></tr>
										<?php endif; ?>
									</tbody>
								</table>
							</div>
						</div>
					</div>
				</div>
			</section>
		</aside>
	</div>
	<script src="<?php echo base_url();?>public/js/jquery.min.js"></script>
	<script src="<?php echo base_url();?>public/js/bootstrap.min.js" type="text/javascript"></script>
	<script src="<?php echo base_url();?>public/js/AdminLTE/app.js" type="text/javascript"></script>
</body>
</html>
