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
				<h1>Doctor Transfers</h1>
				<ol class="breadcrumb">
					<li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
					<li><a href="#">Doctor Module</a></li>
					<li class="active">Transfers</li>
				</ol>
			</section>
			<section class="content">
				<?php echo isset($message) ? $message : ''; ?>

				<div class="row">
					<div class="col-md-6">
						<div class="box box-primary">
							<div class="box-header">
								<h3 class="box-title">Incoming Requests</h3>
							</div>
							<div class="box-body table-responsive no-padding">
								<table class="table table-hover">
									<thead>
										<tr>
											<th>IOP</th>
											<th>Patient</th>
											<th>Status</th>
											<th>Requested</th>
											<th></th>
										</tr>
									</thead>
									<tbody>
										<?php if(isset($incoming) && count($incoming) > 0): ?>
											<?php foreach($incoming as $t): ?>
											<tr>
												<td><?php echo $t->iop_id; ?></td>
												<td><?php echo trim($t->firstname.' '.$t->middlename.' '.$t->lastname); ?></td>
												<td><span class="label label-info"><?php echo $t->status; ?></span></td>
												<td><?php echo $t->requested_at; ?></td>
												<td><a class="btn btn-xs btn-primary" href="<?php echo base_url().'app/doctor_transfer/view/'.$t->transfer_id; ?>">Open</a></td>
											</tr>
											<?php endforeach; ?>
										<?php else: ?>
											<tr><td colspan="5" class="text-muted">No incoming transfer requests.</td></tr>
										<?php endif; ?>
									</tbody>
								</table>
							</div>
						</div>
					</div>

					<div class="col-md-6">
						<div class="box box-primary">
							<div class="box-header">
								<h3 class="box-title">Outgoing Requests</h3>
							</div>
							<div class="box-body table-responsive no-padding">
								<table class="table table-hover">
									<thead>
										<tr>
											<th>IOP</th>
											<th>Patient</th>
											<th>Status</th>
											<th>Requested</th>
											<th></th>
										</tr>
									</thead>
									<tbody>
										<?php if(isset($outgoing) && count($outgoing) > 0): ?>
											<?php foreach($outgoing as $t): ?>
											<tr>
												<td><?php echo $t->iop_id; ?></td>
												<td><?php echo trim($t->firstname.' '.$t->middlename.' '.$t->lastname); ?></td>
												<td><span class="label label-default"><?php echo $t->status; ?></span></td>
												<td><?php echo $t->requested_at; ?></td>
												<td><a class="btn btn-xs btn-primary" href="<?php echo base_url().'app/doctor_transfer/view/'.$t->transfer_id; ?>">Open</a></td>
											</tr>
											<?php endforeach; ?>
										<?php else: ?>
											<tr><td colspan="5" class="text-muted">No outgoing transfer requests.</td></tr>
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
