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
		.vital-alert { background: #fcf8e3; font-weight: bold; }
	</style>
</head>
<body class="skin-blue">
	<?php require_once(APPPATH.'views/include/header.php');?>
	<div class="wrapper row-offcanvas row-offcanvas-left">
		<?php require_once(APPPATH.'views/include/sidebar.php');?>
		<aside class="right-side">
			<section class="content-header">
				<h1>Vitals Detail</h1>
				<ol class="breadcrumb">
					<li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
					<li><a href="#">Nurse Module</a></li>
					<li class="active">Vitals Detail</li>
				</ol>
			</section>

			<section class="content">
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
									<tr><th>Patient No</th><td><?php echo htmlspecialchars(isset($patient_no) ? $patient_no : ''); ?></td></tr>
									<tr><th>IOP ID</th><td><?php echo htmlspecialchars(isset($vitals->iop_id) ? $vitals->iop_id : (isset($iop_no) ? $iop_no : '')); ?></td></tr>
									<tr><th>Name</th><td><?php echo htmlspecialchars($patientName); ?></td></tr>
								</table>
							</div>
						</div>
					</div>

					<div class="col-md-9">
						<div class="box box-primary">
							<div class="box-header">
								<h3 class="box-title">Full Vitals Record</h3>
							</div>
							<div class="box-body">
								<?php
									$pulseValue = isset($vitals->pulse_rate) && is_numeric($vitals->pulse_rate) ? (float)$vitals->pulse_rate : null;
									$tempValue = isset($vitals->temperature) && is_numeric($vitals->temperature) ? (float)$vitals->temperature : null;
									$respValue = isset($vitals->respiration) && is_numeric($vitals->respiration) ? (float)$vitals->respiration : null;
									$spo2Value = isset($vitals->spo2) && is_numeric($vitals->spo2) ? (float)$vitals->spo2 : null;
								?>
								<table class="table table-bordered table-striped">
									<tr>
										<th width="220">Date/Time</th>
										<td><?php echo isset($vitals->dDateTime) && $vitals->dDateTime !== '' ? date("d M Y H:i", strtotime($vitals->dDateTime)) : ''; ?></td>
									</tr>
									<tr>
										<th>BP</th>
										<td><?php echo htmlspecialchars(isset($vitals->bp) ? $vitals->bp : ''); ?></td>
									</tr>
									<tr>
										<th>Pulse</th>
										<td class="numeric <?php echo $pulseValue !== null && ($pulseValue < 60 || $pulseValue > 100) ? 'vital-alert' : ''; ?>"><?php echo htmlspecialchars(isset($vitals->pulse_rate) ? $vitals->pulse_rate : ''); ?></td>
									</tr>
									<tr>
										<th>Temperature</th>
										<td class="numeric <?php echo $tempValue !== null && ($tempValue < 36 || $tempValue > 38) ? 'vital-alert' : ''; ?>"><?php echo htmlspecialchars(isset($vitals->temperature) ? $vitals->temperature : ''); ?></td>
									</tr>
									<tr>
										<th>Respiration</th>
										<td class="numeric <?php echo $respValue !== null && ($respValue < 12 || $respValue > 20) ? 'vital-alert' : ''; ?>"><?php echo htmlspecialchars(isset($vitals->respiration) ? $vitals->respiration : ''); ?></td>
									</tr>
									<tr>
										<th>Weight</th>
										<td class="numeric"><?php echo htmlspecialchars(isset($vitals->weight) ? $vitals->weight : ''); ?></td>
									</tr>
									<tr>
										<th>Height</th>
										<td class="numeric"><?php echo htmlspecialchars(isset($vitals->height) ? $vitals->height : ''); ?></td>
									</tr>
									<tr>
										<th>SPO2</th>
										<td class="numeric <?php echo $spo2Value !== null && $spo2Value < 95 ? 'vital-alert' : ''; ?>"><?php echo htmlspecialchars(isset($vitals->spo2) ? $vitals->spo2 : ''); ?></td>
									</tr>
									<tr>
										<th>Blood Sugar</th>
										<td class="numeric"><?php echo htmlspecialchars(isset($vitals->blood_sugar) ? $vitals->blood_sugar : ''); ?></td>
									</tr>
									<tr>
										<th>Pain Score</th>
										<td class="numeric"><?php echo htmlspecialchars(isset($vitals->pain_score) ? $vitals->pain_score : ''); ?></td>
									</tr>
									<tr>
										<th>Recorded By</th>
										<td><?php echo htmlspecialchars(isset($vitals->cPreparedBy) ? $vitals->cPreparedBy : ''); ?></td>
									</tr>
									<?php if (isset($vitals->remarks)): ?>
										<tr>
											<th>Remarks</th>
											<td><?php echo nl2br(htmlspecialchars($vitals->remarks)); ?></td>
										</tr>
									<?php endif; ?>
								</table>
							</div>
							<div class="box-footer">
								<a class="btn btn-default" href="<?php echo isset($back_url) ? $back_url : base_url().'app/nurse_module/vitals_history'; ?>"><i class="fa fa-arrow-left"></i> Back to History</a>
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
