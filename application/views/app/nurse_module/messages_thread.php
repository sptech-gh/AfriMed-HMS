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
				<h1>Nurse to Doctor Messages</h1>
				<ol class="breadcrumb">
					<li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
					<li><a href="#">Nurse Module</a></li>
					<li class="active">Messages</li>
				</ol>
			</section>

			<section class="content">
				<?php echo isset($message) ? $message : ''; ?>

				<?php if (!isset($messages_ready) || !$messages_ready): ?>
					<div class="alert alert-warning">
						<i class="fa fa-warning"></i>
						Messaging is not installed. Ask an Administrator to run <strong>app/nurse_module/install_enhancements</strong>.
					</div>
				<?php endif; ?>

				<div class="row">
					<div class="col-md-3">
						<div class="box box-primary">
							<div class="box-header">
								<h3 class="box-title">Patient</h3>
							</div>
							<div class="box-body">
								<table class="table table-bordered">
									<tr><th>IOP No</th><td><?php echo isset($getOPDPatient->IO_ID) ? $getOPDPatient->IO_ID : ''; ?></td></tr>
									<tr><th>Patient No</th><td><?php echo isset($patientInfo->patient_no) ? $patientInfo->patient_no : ''; ?></td></tr>
									<tr><th>Name</th><td><?php echo isset($patientInfo->name) ? $patientInfo->name : ''; ?></td></tr>
									<tr><th>Doctor</th><td><?php echo isset($assigned_doctor_id) ? $assigned_doctor_id : ''; ?></td></tr>
								</table>
							</div>
						</div>
					</div>

					<div class="col-md-9">
						<div class="box box-primary">
							<div class="box-header">
								<h3 class="box-title">Conversation</h3>
							</div>
							<div class="box-body" style="max-height:420px; overflow:auto;">
								<?php $me = $this->session->userdata('user_id'); ?>
								<?php if (isset($thread) && is_array($thread) && count($thread) > 0): ?>
									<?php foreach($thread as $m): ?>
										<?php $isMine = (isset($m->from_user_id) && (string)$m->from_user_id === (string)$me); ?>
										<div class="row" style="margin-bottom:10px;">
											<div class="col-xs-12">
												<div style="padding:10px; border-radius:4px; background:<?php echo $isMine ? '#d9edf7' : '#f5f5f5'; ?>; border:1px solid #ddd;">
													<div style="font-size:11px;" class="text-muted">
														<?php echo $isMine ? 'Me' : 'Doctor/Staff'; ?>
														<?php echo isset($m->created_at) ? $m->created_at : ''; ?>
													</div>
													<div><?php echo isset($m->message) ? nl2br(htmlspecialchars($m->message)) : ''; ?></div>
												</div>
											</div>
										</div>
									<?php endforeach; ?>
								<?php else: ?>
									<div class="text-muted">No messages yet.</div>
								<?php endif; ?>
							</div>
							<div class="box-footer">
								<form method="post" action="<?php echo base_url()?>app/nurse_module/send_message" onSubmit="return confirm('Send this message?');">
									<input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
									<input type="hidden" name="opd_no" value="<?php echo isset($getOPDPatient->IO_ID) ? $getOPDPatient->IO_ID : ''; ?>">
									<input type="hidden" name="patient_no" value="<?php echo isset($patientInfo->patient_no) ? $patientInfo->patient_no : ''; ?>">
									<input type="hidden" name="to_doctor_id" value="<?php echo isset($assigned_doctor_id) ? $assigned_doctor_id : ''; ?>">
									<div class="input-group">
										<textarea name="message" class="form-control" rows="2" placeholder="Type a message to the doctor..." required></textarea>
										<span class="input-group-btn">
											<button type="submit" class="btn btn-primary"><i class="fa fa-send"></i> Send</button>
										</span>
									</div>
								</form>
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
