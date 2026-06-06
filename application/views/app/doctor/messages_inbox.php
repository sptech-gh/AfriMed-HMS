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
				<h1>Doctor Messages</h1>
				<ol class="breadcrumb">
					<li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
					<li><a href="#">Doctor Module</a></li>
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

				<div class="box box-primary">
					<div class="box-header">
						<h3 class="box-title">Inbox</h3>
					</div>
					<div class="box-body table-responsive no-padding">
						<table class="table table-hover">
							<thead>
								<tr>
									<th>IOP</th>
									<th>Patient</th>
									<th>Last Message</th>
									<th>Unread</th>
									<th></th>
								</tr>
							</thead>
							<tbody>
								<?php if (isset($inbox) && is_array($inbox) && count($inbox) > 0): ?>
									<?php foreach($inbox as $c): ?>
										<tr>
											<td><?php echo htmlspecialchars((string)$c->iop_id); ?></td>
											<td><?php echo (isset($c->patient_name) && trim((string)$c->patient_name) !== '') ? htmlspecialchars($c->patient_name) : htmlspecialchars((string)$c->patient_no); ?></td>
											<td><?php echo htmlspecialchars((string)$c->last_time); ?></td>
											<td>
												<?php if (isset($c->unread_count) && (int)$c->unread_count > 0): ?>
													<span class="label label-danger"><?php echo (int)$c->unread_count; ?></span>
												<?php else: ?>
													<span class="text-muted">0</span>
												<?php endif; ?>
											</td>
											<td>
												<a class="btn btn-xs btn-primary" href="<?php echo base_url().'app/doctor_messages/thread/'.$c->iop_id.'/'.$c->patient_no; ?>">Open</a>
											</td>
										</tr>
									<?php endforeach; ?>
								<?php else: ?>
									<tr><td colspan="5" class="text-muted">No messages.</td></tr>
								<?php endif; ?>
							</tbody>
						</table>
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
