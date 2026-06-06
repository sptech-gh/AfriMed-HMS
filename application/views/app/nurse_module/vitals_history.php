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
				<h1>Patient Vitals History</h1>
				<ol class="breadcrumb">
					<li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
					<li><a href="#">Nurse Module</a></li>
					<li class="active">Vitals History</li>
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
								<h3 class="box-title">Vitals Records</h3>
							</div>
							<div class="box-body table-responsive no-padding">
								<table class="table table-hover table-striped">
									<thead>
										<tr>
											<th>Date/Time</th>
											<th>BP</th>
											<th class="numeric">Pulse</th>
											<th class="numeric">Temperature</th>
											<th class="numeric">Respiration</th>
											<th class="numeric">Weight</th>
											<th class="numeric">SPO2</th>
											<th>Recorded By</th>
											<th>Action</th>
										</tr>
									</thead>
									<tbody>
										<?php if (isset($vitals_history) && is_array($vitals_history) && count($vitals_history) > 0): ?>
											<?php foreach($vitals_history as $row): ?>
												<?php
													$pulseValue = isset($row->pulse_rate) && is_numeric($row->pulse_rate) ? (float)$row->pulse_rate : null;
													$tempValue = isset($row->temperature) && is_numeric($row->temperature) ? (float)$row->temperature : null;
													$respValue = isset($row->respiration) && is_numeric($row->respiration) ? (float)$row->respiration : null;
													$spo2Value = isset($row->spo2) && is_numeric($row->spo2) ? (float)$row->spo2 : null;
												?>
												<tr>
													<td><?php echo isset($row->dDateTime) && $row->dDateTime !== '' ? date("d M Y H:i", strtotime($row->dDateTime)) : ''; ?></td>
													<td><?php echo htmlspecialchars(isset($row->bp) ? $row->bp : ''); ?></td>
													<td class="numeric <?php echo $pulseValue !== null && ($pulseValue < 60 || $pulseValue > 100) ? 'vital-alert' : ''; ?>"><?php echo htmlspecialchars(isset($row->pulse_rate) ? $row->pulse_rate : ''); ?></td>
													<td class="numeric <?php echo $tempValue !== null && ($tempValue < 36 || $tempValue > 38) ? 'vital-alert' : ''; ?>"><?php echo htmlspecialchars(isset($row->temperature) ? $row->temperature : ''); ?></td>
													<td class="numeric <?php echo $respValue !== null && ($respValue < 12 || $respValue > 20) ? 'vital-alert' : ''; ?>"><?php echo htmlspecialchars(isset($row->respiration) ? $row->respiration : ''); ?></td>
													<td class="numeric"><?php echo htmlspecialchars(isset($row->weight) ? $row->weight : ''); ?></td>
													<td class="numeric <?php echo $spo2Value !== null && $spo2Value < 95 ? 'vital-alert' : ''; ?>"><?php echo htmlspecialchars(isset($row->spo2) ? $row->spo2 : ''); ?></td>
													<td><?php echo htmlspecialchars(isset($row->cPreparedBy) ? $row->cPreparedBy : ''); ?></td>
													<td><a class="btn btn-xs btn-default" href="<?php echo base_url().'app/nurse_module/vitals_detail/'.(isset($row->vital_id) ? $row->vital_id : 0).'/'.(isset($row->iop_id) ? $row->iop_id : (isset($iop_no) ? $iop_no : '')).'/'.(isset($patient_no) ? $patient_no : ''); ?>">View</a></td>
												</tr>
											<?php endforeach; ?>
										<?php else: ?>
											<tr><td colspan="9" class="text-muted">No vitals records found for this patient.</td></tr>
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
