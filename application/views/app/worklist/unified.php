<?php $this->load->view('app/layouts/header'); ?>
<?php $this->load->view('app/layouts/sidebar'); ?>

<div class="content-wrapper">
	<section class="content-header">
		<h1><?php echo isset($title) ? $title : 'Unified Worklist'; ?></h1>
	</section>

	<section class="content">
		<div style="margin-bottom:10px">
			<a class="btn btn-default<?php echo (isset($status) && $status === 'PENDING') ? ' btn-primary' : ''; ?>" href="<?php echo base_url('app/worklist/unified?status=PENDING'); ?>">Pending</a>
			<a class="btn btn-default<?php echo (isset($status) && $status === 'COMPLETED') ? ' btn-primary' : ''; ?>" href="<?php echo base_url('app/worklist/unified?status=COMPLETED'); ?>">Completed</a>
		</div>

		<div class="box">
			<div class="box-body table-responsive">
				<table class="table table-hover table-striped">
					<thead>
						<tr>
							<th>Patient</th>
							<th>Service</th>
							<th>Module</th>
							<th>Status</th>
							<th>Payment</th>
							<th>Created</th>
							<th>Action</th>
						</tr>
					</thead>
					<tbody>
						<?php if (!isset($items) || empty($items)): ?>
							<tr><td colspan="7">No worklist items</td></tr>
						<?php else: ?>
							<?php foreach ($items as $it): ?>
								<?php
									$module = isset($it['module']) ? $it['module'] : '';
									$wf = isset($it['workflow_status']) ? strtoupper((string)$it['workflow_status']) : 'PENDING';
									$pay = isset($it['payment_status']) ? strtoupper((string)$it['payment_status']) : 'UNBILLED';
									$payer = isset($it['payer_type']) ? strtoupper((string)$it['payer_type']) : '';
									$can = isset($it['can_proceed']) ? (bool)$it['can_proceed'] : false;
									$reason = isset($it['blocked_reason']) ? (string)$it['blocked_reason'] : '';

									$modLabel = '<span class="label label-default">' . htmlspecialchars($module, ENT_QUOTES, 'UTF-8') . '</span>';
									if ($module === 'LAB') { $modLabel = '<span class="label label-info">LAB</span>'; }
									if ($module === 'RADIOLOGY') { $modLabel = '<span class="label label-warning">RAD</span>'; }
									if ($module === 'SONOGRAPHY') { $modLabel = '<span class="label label-primary">SONO</span>'; }

									$wfBadge = '<span class="label label-default">' . htmlspecialchars($wf, ENT_QUOTES, 'UTF-8') . '</span>';
									if ($wf === 'COMPLETED') { $wfBadge = '<span class="label label-success">COMPLETED</span>'; }
									if ($wf === 'IN_PROGRESS') { $wfBadge = '<span class="label label-info">IN PROGRESS</span>'; }

									$payBadge = '<span class="label label-default">UNBILLED</span>';
									if ($pay === 'PAID' && $payer === 'NHIS') {
										$payBadge = '<span class="label label-primary">NHIS</span>';
									} else if ($pay === 'PAID') {
										$payBadge = '<span class="label label-success">PAID</span>';
									} else if ($pay === 'PARTIAL') {
										$payBadge = '<span class="label label-warning">PARTIAL</span>';
									} else if ($pay === 'PENDING') {
										$payBadge = '<span class="label label-warning">PENDING</span>';
									} else if ($pay === 'ERROR') {
										$payBadge = '<span class="label label-danger">ERROR</span>';
									}

									$actionUrl = '#';
									if ($module === 'LAB') {
										$actionUrl = base_url('app/laboratory/results/' . urlencode((string)$it['item_id']) . '/' . urlencode((string)$it['iop_id']));
									} else if ($module === 'RADIOLOGY') {
										$actionUrl = base_url('app/radiology/result_entry/' . urlencode((string)$it['item_id']));
									} else if ($module === 'SONOGRAPHY') {
										$src = isset($it['source_id']) ? (string)$it['source_id'] : (string)$it['item_id'];
										$actionUrl = base_url('app/sonography/results/' . urlencode((string)$src) . '/' . urlencode((string)$it['iop_id']));
									}

									$patient = (isset($it['patient_no']) ? $it['patient_no'] : '') . ' - ' . (isset($it['patient_name']) ? $it['patient_name'] : '');
								?>
								<tr>
									<td><?php echo htmlspecialchars(trim((string)$patient), ENT_QUOTES, 'UTF-8'); ?></td>
									<td><?php echo htmlspecialchars((string)$it['service_name'], ENT_QUOTES, 'UTF-8'); ?></td>
									<td><?php echo $modLabel; ?></td>
									<td><?php echo $wfBadge; ?></td>
									<td><?php echo $payBadge; ?></td>
									<td><?php echo htmlspecialchars((string)$it['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
									<td>
										<?php if (!$can): ?>
											<button class="btn btn-xs btn-default" disabled="disabled" title="<?php echo htmlspecialchars($reason, ENT_QUOTES, 'UTF-8'); ?>">Blocked</button>
										<?php else: ?>
											<a class="btn btn-xs btn-primary" href="<?php echo $actionUrl; ?>">Open</a>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
	</section>
</div>

<?php $this->load->view('app/layouts/footer'); ?>
