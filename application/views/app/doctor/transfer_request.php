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
</head>
<body class="skin-blue">
	<?php require_once(APPPATH.'views/include/header.php');?>
	<div class="wrapper row-offcanvas row-offcanvas-left">
		<?php require_once(APPPATH.'views/include/sidebar.php');?>
		<aside class="right-side">
			<section class="content-header">
				<h1>Transfer Patient</h1>
				<ol class="breadcrumb">
					<li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
					<li><a href="#">Doctor Module</a></li>
					<li><a href="<?php echo base_url()?>app/doctor_transfer/inbox">Transfers</a></li>
					<li class="active">New Request</li>
				</ol>
			</section>
			<section class="content">
				<?php echo isset($message) ? $message : ''; ?>
				<div class="box box-primary">
					<div class="box-header">
						<h3 class="box-title">Request Transfer</h3>
					</div>
					<div class="box-body">
						<form method="post" action="<?php echo base_url().'app/doctor_transfer/submit_request'; ?>">
							<input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
							<input type="hidden" name="iop_id" value="<?php echo htmlspecialchars($encounter->IO_ID); ?>">
							<input type="hidden" name="patient_no" value="<?php echo htmlspecialchars($encounter->patient_no); ?>">

							<div class="form-group">
								<label>Patient</label>
								<div class="form-control" style="height:auto;">
									<strong><?php echo htmlspecialchars(isset($patientInfo->name) ? $patientInfo->name : ''); ?></strong>
									<div class="text-muted">IOP: <?php echo htmlspecialchars($encounter->IO_ID); ?> | Type: <?php echo htmlspecialchars($encounter->patient_type); ?></div>
								</div>
							</div>

							<div class="form-group">
								<label>Transfer To Doctor</label>
								<select name="to_doctor_id" class="form-control" required>
									<option value="">-- Select Doctor --</option>
									<?php if(isset($doctorList) && count($doctorList) > 0): ?>
										<?php foreach($doctorList as $d): ?>
											<?php if((string)$d->user_id !== (string)$this->session->userdata('user_id')): ?>
											<option value="<?php echo $d->user_id; ?>"><?php echo htmlspecialchars($d->name); ?></option>
											<?php endif; ?>
										<?php endforeach; ?>
									<?php endif; ?>
								</select>
							</div>

							<div class="form-group">
								<label>Urgency</label>
								<select name="urgency_level" class="form-control" required>
									<option value="NORMAL">Normal</option>
									<option value="URGENT">Urgent</option>
									<option value="CRITICAL">Critical</option>
								</select>
							</div>

							<div class="form-group">
								<label>Reason (Category)</label>
								<select name="reason_code" class="form-control">
									<option value="">-- Select Reason --</option>
									<option value="WORKLOAD">Workload / Queue balancing</option>
									<option value="SPECIALIST">Needs Specialist</option>
									<option value="FOLLOW_UP">Follow-up / Continuity</option>
									<option value="SHIFT_HANDOVER">Shift / Duty Handover</option>
									<option value="REFERRAL">Referral</option>
									<option value="OTHER">Other</option>
								</select>
							</div>

							<div class="form-group">
								<label>Reason</label>
								<textarea name="reason" class="form-control" rows="3" placeholder="Why are you transferring this patient?"></textarea>
							</div>

							<div class="form-group">
								<label>Handover Notes (optional)</label>
								<textarea name="handover_note" class="form-control" rows="5" placeholder="Key clinical context, pending tests, plan, red flags..."></textarea>
							</div>

							<button type="submit" class="btn btn-primary"><i class="fa fa-send"></i> Submit Transfer Request</button>
							<a class="btn btn-default" href="<?php echo base_url().'app/doctor_transfer/inbox'; ?>">Cancel</a>
						</form>
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
