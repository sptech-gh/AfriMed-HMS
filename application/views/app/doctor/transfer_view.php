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
				<h1>Transfer Details</h1>
				<ol class="breadcrumb">
					<li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
					<li><a href="#">Doctor Module</a></li>
					<li><a href="<?php echo base_url()?>app/doctor_transfer/inbox">Transfers</a></li>
					<li class="active">View</li>
				</ol>
			</section>
			<section class="content">
				<?php echo isset($message) ? $message : ''; ?>
				<?php if (isset($is_first_view) && $is_first_view) { ?>
					<div class="alert alert-info">
						<strong>Handover:</strong> This is your first time opening this transfer. Please review the summary and checklist.
					</div>
				<?php } ?>

				<div class="row">
					<div class="col-md-6">
						<div class="box box-primary">
							<div class="box-header">
								<h3 class="box-title">Summary</h3>
							</div>
							<div class="box-body">
								<p><strong>Patient:</strong> <?php echo htmlspecialchars(isset($patientInfo->name) ? $patientInfo->name : ''); ?></p>
								<p><strong>IOP:</strong> <?php echo htmlspecialchars($transfer->iop_id); ?> <strong>Type:</strong> <?php echo htmlspecialchars($transfer->patient_type); ?></p>
								<p><strong>Status:</strong> <span class="label label-info"><?php echo htmlspecialchars($transfer->status); ?></span></p>
								<p><strong>Requested:</strong> <?php echo htmlspecialchars($transfer->requested_at); ?></p>
								<?php if (isset($transfer->urgency_level) && trim((string)$transfer->urgency_level) !== '') { ?>
									<p><strong>Urgency:</strong> <?php echo htmlspecialchars($transfer->urgency_level); ?></p>
								<?php } ?>
								<?php if (isset($transfer->reason_code) && trim((string)$transfer->reason_code) !== '') { ?>
									<p><strong>Reason Category:</strong> <?php echo htmlspecialchars($transfer->reason_code); ?></p>
								<?php } ?>
								<?php if (!empty($transfer->reason)): ?>
									<p><strong>Reason:</strong><br><?php echo nl2br(htmlspecialchars($transfer->reason)); ?></p>
								<?php endif; ?>
							</div>
						</div>
					</div>

					<?php if (isset($handover_checklist) && is_array($handover_checklist) && count($handover_checklist) > 0) { ?>
					<div class="col-md-12">
						<div class="box box-primary">
							<div class="box-header">
								<h3 class="box-title">Auto-Generated Handover Checklist (Snapshot)</h3>
							</div>
							<div class="box-body">
								<div class="row">
									<div class="col-md-4">
										<strong>Pending Labs</strong>
										<div class="text-muted" style="margin-bottom:10px;">
											<?php
											$pl = isset($handover_checklist['pending_labs']) && is_array($handover_checklist['pending_labs']) ? $handover_checklist['pending_labs'] : array();
											echo count($pl) ? htmlspecialchars(implode(', ', $pl)) : 'None';
											?>
										</div>
									</div>
									<div class="col-md-4">
										<strong>Pending Imaging / Sonography</strong>
										<div class="text-muted" style="margin-bottom:10px;">
											<?php
											$pi = isset($handover_checklist['pending_imaging']) && is_array($handover_checklist['pending_imaging']) ? $handover_checklist['pending_imaging'] : array();
											echo count($pi) ? htmlspecialchars(implode(', ', $pi)) : 'None';
											?>
										</div>
									</div>
									<div class="col-md-4">
										<strong>Active Medications</strong>
										<div class="text-muted" style="margin-bottom:10px;">
											<?php
											$am = isset($handover_checklist['active_medications']) && is_array($handover_checklist['active_medications']) ? $handover_checklist['active_medications'] : array();
											echo count($am) ? htmlspecialchars(implode(', ', $am)) : 'None';
											?>
										</div>
									</div>
								</div>

								<div class="row">
									<div class="col-md-6">
										<strong>Allergies</strong>
										<div class="text-muted" style="margin-bottom:10px;">
											<?php echo htmlspecialchars(isset($handover_checklist['allergies']) ? (string)$handover_checklist['allergies'] : ''); ?>
										</div>
									</div>
									<div class="col-md-6">
										<strong>Warnings</strong>
										<div class="text-muted" style="margin-bottom:10px;">
											<?php echo htmlspecialchars(isset($handover_checklist['warnings']) ? (string)$handover_checklist['warnings'] : ''); ?>
										</div>
									</div>
								</div>

								<?php
								$billingFlags = isset($handover_checklist['billing_flags']) && is_array($handover_checklist['billing_flags']) ? $handover_checklist['billing_flags'] : array();
								$criticalUnpaid = isset($billingFlags['critical_unpaid_items']) ? (bool)$billingFlags['critical_unpaid_items'] : false;
								?>
								<div class="alert <?php echo $criticalUnpaid ? 'alert-warning' : 'alert-success'; ?>" style="margin-top:10px;">
									<strong>Billing Flag:</strong> <?php echo $criticalUnpaid ? 'Critical unpaid items may exist (flag only).' : 'No critical unpaid flags detected.'; ?>
								</div>
							</div>
						</div>
					</div>
					<?php } ?>

					<div class="col-md-6">
						<div class="box box-primary">
							<div class="box-header">
								<h3 class="box-title">Actions</h3>
							</div>
							<div class="box-body">
								<?php $me = (string)$this->session->userdata('user_id'); ?>
								<?php
								$openEncounterUrl = '';
								if (isset($transfer->patient_type) && $transfer->patient_type == 'IPD') {
									$openEncounterUrl = base_url().'app/ipd/view/'.$transfer->iop_id.'/'.$transfer->patient_no;
								} else if (isset($transfer->patient_type) && $transfer->patient_type == 'OPD') {
									$openEncounterUrl = base_url().'app/opd/view/'.$transfer->iop_id.'/'.$transfer->patient_no;
								}
								?>
								<?php if ($openEncounterUrl): ?>
									<a class="btn btn-primary" target="_blank" href="<?php echo $openEncounterUrl; ?>" style="margin-bottom:10px;"><i class="fa fa-folder-open"></i> Open <?php echo htmlspecialchars($transfer->patient_type); ?> Record</a>
								<?php endif; ?>
								<?php if ($transfer->status === 'PENDING' && (string)$transfer->to_doctor_id === $me): ?>
									<form method="post" action="<?php echo base_url().'app/doctor_transfer/accept/'.$transfer->transfer_id; ?>" style="margin-bottom:10px;">
									<input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
									<div class="form-group">
											<label>Acceptance Note (optional)</label>
											<textarea name="handover_note" class="form-control" rows="3"></textarea>
										</div>
										<button type="submit" class="btn btn-success"><i class="fa fa-check"></i> Accept & Take Over</button>
									</form>

									<form method="post" action="<?php echo base_url().'app/doctor_transfer/reject/'.$transfer->transfer_id; ?>" style="margin-bottom:10px;">
									<input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
									<div class="form-group">
											<label>Rejection Note (optional)</label>
											<textarea name="note" class="form-control" rows="3"></textarea>
										</div>
										<button type="submit" class="btn btn-danger"><i class="fa fa-times"></i> Reject</button>
									</form>
								<?php endif; ?>

								<?php if ($transfer->status === 'PENDING' && (string)$transfer->from_doctor_id === $me): ?>
									<form method="post" action="<?php echo base_url().'app/doctor_transfer/cancel/'.$transfer->transfer_id; ?>" style="display:inline;" onsubmit="return confirm('Cancel this transfer request?')">
									<input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
									<button type="submit" class="btn btn-warning"><i class="fa fa-ban"></i> Cancel Request</button>
									</form>
								<?php endif; ?>

								<a class="btn btn-default" href="<?php echo base_url().'app/doctor_transfer/inbox'; ?>">Back to Inbox</a>
							</div>
						</div>
					</div>
				</div>

				<div class="box box-primary">
					<div class="box-header">
						<h3 class="box-title">Handover Notes</h3>
					</div>
					<div class="box-body">
						<?php if(isset($notes) && count($notes) > 0): ?>
							<?php foreach($notes as $n): ?>
								<div class="well well-sm">
									<div class="text-muted" style="font-size:12px;"><?php echo htmlspecialchars($n->created_at); ?> | Author: <?php echo htmlspecialchars($n->author_user_id); ?></div>
									<div><?php echo nl2br(htmlspecialchars($n->note)); ?></div>
								</div>
							<?php endforeach; ?>
						<?php else: ?>
							<div class="text-muted">No notes yet.</div>
						<?php endif; ?>

						<form method="post" action="<?php echo base_url().'app/doctor_transfer/add_note/'.$transfer->transfer_id; ?>">
							<input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
							<div class="form-group">
								<label>Add Note</label>
								<textarea name="note" class="form-control" rows="4" placeholder="Add clinical context, pending investigations, plan..."></textarea>
							</div>
							<button type="submit" class="btn btn-primary"><i class="fa fa-plus"></i> Add Note</button>
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
