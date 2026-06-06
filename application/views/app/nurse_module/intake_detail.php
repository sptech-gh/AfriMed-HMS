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
				<h1>Intake Detail</h1>
				<ol class="breadcrumb">
					<li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
					<li><a href="#">Nurse Module</a></li>
					<li class="active">Intake Detail</li>
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
								<table class="table table-bordered">
									<tr><th>Patient No</th><td><?php echo htmlspecialchars(isset($patient_no) ? $patient_no : ''); ?></td></tr>
									<tr><th>IOP ID</th><td><?php echo htmlspecialchars(isset($intake->iop_id) ? $intake->iop_id : (isset($iop_no) ? $iop_no : '')); ?></td></tr>
									<tr><th>Name</th><td><?php echo htmlspecialchars(isset($patientInfo->name) ? $patientInfo->name : ''); ?></td></tr>
								</table>
							</div>
						</div>
					</div>

					<div class="col-md-9">
						<div class="box box-primary">
							<div class="box-header">
								<h3 class="box-title">Full Intake Record</h3>
							</div>
							<div class="box-body">
								<?php $oralValue = isset($intake->oral) ? (int)$intake->oral : 0; ?>
								<?php $ivValue = isset($intake->IV_fluids) ? (int)$intake->IV_fluids : 0; ?>
								<?php $bloodValue = isset($intake->blood_loss) ? (int)$intake->blood_loss : 0; ?>
								<table class="table table-bordered table-striped">
									<tr>
										<th width="220">Date/Time</th>
										<td><?php echo isset($intake->dDateTime) && $intake->dDateTime !== '' ? date("d M Y H:i", strtotime($intake->dDateTime)) : ''; ?></td>
									</tr>
									<tr>
										<th>Oral Intake</th>
										<td class="numeric <?php echo $oralValue >= 1000 ? 'high-intake' : ''; ?>"><?php echo htmlspecialchars(isset($intake->oral) ? $intake->oral : ''); ?></td>
									</tr>
									<tr>
										<th>IV Intake</th>
										<td class="numeric <?php echo $ivValue >= 1000 ? 'high-intake' : ''; ?>"><?php echo htmlspecialchars(isset($intake->IV_fluids) ? $intake->IV_fluids : ''); ?></td>
									</tr>
									<tr>
										<th>Blood Loss</th>
										<td class="numeric <?php echo $bloodValue >= 500 ? 'high-intake' : ''; ?>"><?php echo htmlspecialchars(isset($intake->blood_loss) ? $intake->blood_loss : ''); ?></td>
									</tr>
									<tr>
										<th>No. of Stool</th>
										<td class="numeric"><?php echo htmlspecialchars(isset($intake->no_stool) ? $intake->no_stool : ''); ?></td>
									</tr>
									<tr>
										<th>No. of Urine</th>
										<td class="numeric"><?php echo htmlspecialchars(isset($intake->no_urine) ? $intake->no_urine : ''); ?></td>
									</tr>
									<tr>
										<th>Remarks / Particulars</th>
										<td><?php echo nl2br(htmlspecialchars(isset($intake->particulars) ? $intake->particulars : '')); ?></td>
									</tr>
									<tr>
										<th>Recorded By</th>
										<td><?php echo htmlspecialchars(isset($intake->cPreparedBy) ? $intake->cPreparedBy : ''); ?></td>
									</tr>
								</table>
							</div>
							<div class="box-footer">
								<a class="btn btn-default" href="<?php echo isset($back_url) ? $back_url : base_url().'app/nurse_module/intake_history'; ?>"><i class="fa fa-arrow-left"></i> Back to History</a>
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
