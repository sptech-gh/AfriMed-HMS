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
				<h1>Intake/Output Balance Detail</h1>
				<ol class="breadcrumb">
					<li><a href="<?php echo base_url()?>app"><i class="fa fa-home"></i> Home</a></li>
					<li><a href="#">Nurse Module</a></li>
					<li class="active">Balance Detail</li>
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
								<h3 class="box-title"><?php echo isset($type) && $type === 'output' ? 'Output Detail' : 'Intake Detail'; ?></h3>
							</div>
							<div class="box-body">
								<?php
									$isOutput = isset($type) && $type === 'output';
									$intakeTotal = 0;
									$outputTotal = 0;
									if (isset($record) && $record) {
										if ($isOutput) {
											$outputTotal = (float)(isset($record->urine) ? $record->urine : 0) + (float)(isset($record->feaces) ? $record->feaces : 0) + (float)(isset($record->respitation) ? $record->respitation : 0) + (float)(isset($record->skin) ? $record->skin : 0);
										} else {
											$intakeTotal = (float)(isset($record->IV_fluids) ? $record->IV_fluids : 0) + (float)(isset($record->oral) ? $record->oral : 0);
											if (isset($record->blood_loss)) {
												$intakeTotal += (float)$record->blood_loss;
											}
										}
									}
									$balance = $intakeTotal - $outputTotal;
								?>
								<table class="table table-bordered">
									<tr><th style="width: 220px;">Date/Time</th><td><?php echo isset($record->dDateTime) && $record->dDateTime !== '' ? date("d M Y H:i", strtotime($record->dDateTime)) : ''; ?></td></tr>
									<?php if ($isOutput): ?>
										<tr><th>Urine</th><td class="numeric"><?php echo htmlspecialchars(isset($record->urine) ? $record->urine : ''); ?></td></tr>
										<tr><th>Feaces</th><td class="numeric"><?php echo htmlspecialchars(isset($record->feaces) ? $record->feaces : ''); ?></td></tr>
										<tr><th>Respiration</th><td class="numeric"><?php echo htmlspecialchars(isset($record->respitation) ? $record->respitation : ''); ?></td></tr>
										<tr><th>Skin</th><td class="numeric"><?php echo htmlspecialchars(isset($record->skin) ? $record->skin : ''); ?></td></tr>
									<?php else: ?>
										<tr><th>Oral Intake</th><td class="numeric"><?php echo htmlspecialchars(isset($record->oral) ? $record->oral : ''); ?></td></tr>
										<tr><th>IV Intake</th><td class="numeric"><?php echo htmlspecialchars(isset($record->IV_fluids) ? $record->IV_fluids : ''); ?></td></tr>
										<?php if (isset($record->blood_loss)): ?>
											<tr><th>Blood Loss</th><td class="numeric"><?php echo htmlspecialchars($record->blood_loss); ?></td></tr>
										<?php endif; ?>
										<tr><th>No. of Stool</th><td class="numeric"><?php echo htmlspecialchars(isset($record->no_stool) ? $record->no_stool : ''); ?></td></tr>
										<tr><th>No. of Urine</th><td class="numeric"><?php echo htmlspecialchars(isset($record->no_urine) ? $record->no_urine : ''); ?></td></tr>
									<?php endif; ?>
									<tr><th>Balance Contribution</th><td class="numeric <?php echo $balance < 0 ? 'negative-balance' : ''; ?>"><?php echo number_format($balance, 2); ?></td></tr>
									<?php if (isset($record->particulars)): ?>
										<tr><th>Remarks/Particulars</th><td><?php echo htmlspecialchars($record->particulars); ?></td></tr>
									<?php endif; ?>
									<tr><th>Recorded By</th><td><?php echo htmlspecialchars(isset($record->cPreparedBy) ? $record->cPreparedBy : ''); ?></td></tr>
								</table>
							</div>
							<div class="box-footer">
								<a class="btn btn-default" href="<?php echo isset($back_url) ? $back_url : base_url().'app/nurse_module/io_balance_history'; ?>"><i class="fa fa-arrow-left"></i> Back to History</a>
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
