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
		.high-intake { background: #fcf8e3; font-weight: bold; }
	</style>
</head>
<body class="skin-blue">
	<?php require_once(APPPATH.'views/include/header.php');?>
	<div class="wrapper row-offcanvas row-offcanvas-left">
		<?php require_once(APPPATH.'views/include/sidebar.php');?>
		<aside class="right-side">
			<section class="content-header">
				<h1>Patient Intake History</h1>
				<ol class="breadcrumb">
					<li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
					<li><a href="#">Nurse Module</a></li>
					<li class="active">Intake History</li>
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
								<table class="table table-bordered">
									<tr><th>IOP ID</th><td><?php echo htmlspecialchars(isset($iop_no) ? $iop_no : ''); ?></td></tr>
									<tr><th>Patient No</th><td><?php echo htmlspecialchars(isset($patient_no) ? $patient_no : ''); ?></td></tr>
									<tr><th>Name</th><td><?php echo htmlspecialchars(isset($patientInfo->name) ? $patientInfo->name : ''); ?></td></tr>
								</table>
							</div>
						</div>
					</div>

					<div class="col-md-9">
						<div class="box box-primary">
							<div class="box-header">
								<h3 class="box-title">Intake Records</h3>
							</div>
							<div class="box-body table-responsive no-padding">
								<table class="table table-hover table-striped">
									<thead>
										<tr>
											<th>Date/Time</th>
											<th>Patient No</th>
											<th>IOP ID</th>
											<th class="numeric">Oral Intake</th>
											<th class="numeric">IV Intake</th>
											<th>Recorded By</th>
											<th>Remarks</th>
											<th></th>
										</tr>
									</thead>
									<tbody>
										<?php if (isset($intake_history) && is_array($intake_history) && count($intake_history) > 0): ?>
											<?php foreach($intake_history as $row): ?>
												<?php $oralValue = isset($row->oral) ? (int)$row->oral : 0; ?>
												<?php $ivValue = isset($row->IV_fluids) ? (int)$row->IV_fluids : 0; ?>
												<tr>
													<td><?php echo isset($row->dDateTime) && $row->dDateTime !== '' ? date("d M Y H:i", strtotime($row->dDateTime)) : ''; ?></td>
													<td><?php echo htmlspecialchars(isset($patient_no) ? $patient_no : ''); ?></td>
													<td><?php echo htmlspecialchars(isset($row->iop_id) ? $row->iop_id : ''); ?></td>
													<td class="numeric <?php echo $oralValue >= 1000 ? 'high-intake' : ''; ?>"><?php echo htmlspecialchars(isset($row->oral) ? $row->oral : ''); ?></td>
													<td class="numeric <?php echo $ivValue >= 1000 ? 'high-intake' : ''; ?>"><?php echo htmlspecialchars(isset($row->IV_fluids) ? $row->IV_fluids : ''); ?></td>
													<td><?php echo htmlspecialchars(isset($row->cPreparedBy) ? $row->cPreparedBy : ''); ?></td>
													<td><?php echo htmlspecialchars(isset($row->particulars) ? $row->particulars : ''); ?></td>
													<td><a href="<?php echo base_url()?>app/nurse_module/intake_detail/<?php echo isset($row->intake_id) ? $row->intake_id : 0; ?>/<?php echo isset($row->iop_id) ? $row->iop_id : ''; ?>/<?php echo isset($patient_no) ? $patient_no : ''; ?>">View</a></td>
												</tr>
											<?php endforeach; ?>
										<?php else: ?>
											<tr><td colspan="8" class="text-muted">No intake records found for this patient.</td></tr>
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
