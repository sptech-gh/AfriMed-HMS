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
	<link rel="stylesheet" href="<?php echo base_url();?>public/datepicker/css/datepicker.css">
	<style>
		.priority-URGENT { color:#fff; background:#c0392b; }
		.priority-HIGH   { color:#fff; background:#e67e22; }
		.priority-NORMAL { color:#fff; background:#3498db; }
		.cat-badge { font-size:10px; padding:2px 6px; border-radius:3px; background:#95a5a6; color:#fff; }
		.cat-MEDICATION_ROUND    { background:#8e44ad; }
		.cat-VITALS_CHECK        { background:#2980b9; }
		.cat-WOUND_CARE          { background:#c0392b; }
		.cat-IV_CARE             { background:#16a085; }
		.cat-PATIENT_MONITORING  { background:#2c3e50; }
		.cat-SPECIMEN_COLLECTION { background:#d35400; }
		.cat-DISCHARGE_PREP      { background:#27ae60; }
		.cat-ADMISSION_PREP      { background:#2980b9; }
		.cat-FEEDING             { background:#f39c12; }
		.cat-HANDOVER_NOTE       { background:#7f8c8d; }
		.task-assignee { font-size:11px; color:#7f8c8d; }
	</style>
</head>
<body class="skin-blue">
	<?php require_once(APPPATH.'views/include/header.php');?>
	<div class="wrapper row-offcanvas row-offcanvas-left">
		<?php require_once(APPPATH.'views/include/sidebar.php');?>
		<aside class="right-side">
			<section class="content-header">
				<h1>Shift Tasks / Handover</h1>
				<ol class="breadcrumb">
					<li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
					<li><a href="#">Nurse Module</a></li>
					<li class="active">Shift Tasks</li>
				</ol>
			</section>

			<section class="content">
				<?php echo isset($message) ? $message : ''; ?>

				<?php if (!isset($tasks_ready) || !$tasks_ready): ?>
					<div class="alert alert-warning">
						<i class="fa fa-warning"></i>
						Shift tasks are not installed. Ask an Administrator to run <strong>app/nurse_module/install_enhancements</strong>.
					</div>
				<?php endif; ?>

				<!-- Filter Bar -->
				<div class="box box-default" style="margin-bottom:10px;">
					<div class="box-body">
						<form method="post" action="<?php echo base_url()?>app/nurse_module/shift_tasks" class="form-inline">
							<input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
							<div class="form-group" style="margin-right:10px;">
								<label>Shift: </label>
								<select name="shift_id" class="form-control input-sm">
									<option value="">All Shifts</option>
									<?php if (isset($shifts) && is_array($shifts)): ?>
										<?php foreach($shifts as $s): ?>
											<option value="<?php echo $s->shift_id; ?>" <?php echo ((string)$selected_shift_id === (string)$s->shift_id) ? 'selected' : ''; ?>>
												<?php echo htmlspecialchars($s->shift_name); ?> (<?php echo substr($s->start_time,0,5); ?>–<?php echo substr($s->end_time,0,5); ?>)
											</option>
										<?php endforeach; ?>
									<?php endif; ?>
								</select>
							</div>
							<div class="form-group" style="margin-right:10px;">
								<label>Date: </label>
								<input type="text" name="shift_date" id="shift_date" class="form-control input-sm" value="<?php echo isset($shift_date) ? htmlspecialchars($shift_date) : date('Y-m-d'); ?>" style="width:120px;">
							</div>
							<button type="submit" class="btn btn-sm btn-primary"><i class="fa fa-filter"></i> Filter</button>
						</form>
					</div>
				</div>

				<div class="row">
					<!-- Left: Add Task Form -->
					<div class="col-md-4">
						<div class="box box-primary">
							<div class="box-header with-border">
								<h3 class="box-title"><i class="fa fa-plus-circle"></i> Create Task</h3>
							</div>
							<div class="box-body">
								<form method="post" action="<?php echo base_url()?>app/nurse_module/save_shift_task" onSubmit="return confirm('Create this task?');">
									<input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
									<input type="hidden" name="shift_date" value="<?php echo isset($shift_date) ? htmlspecialchars($shift_date) : date('Y-m-d'); ?>">

									<div class="form-group">
										<label>Shift</label>
										<select name="shift_id" class="form-control input-sm" required>
											<?php if (isset($shifts) && is_array($shifts) && count($shifts) > 0): ?>
												<?php foreach($shifts as $s): ?>
													<option value="<?php echo $s->shift_id; ?>" <?php echo ((string)$selected_shift_id === (string)$s->shift_id) ? 'selected' : ''; ?>><?php echo htmlspecialchars($s->shift_name); ?></option>
												<?php endforeach; ?>
											<?php else: ?>
												<option value="">No shifts</option>
											<?php endif; ?>
										</select>
									</div>
									<div class="form-group">
										<label>Task Category</label>
										<select name="task_category" class="form-control input-sm" required>
											<?php if (isset($task_categories)): ?>
												<?php foreach($task_categories as $key => $label): ?>
													<option value="<?php echo $key; ?>"><?php echo htmlspecialchars($label); ?></option>
												<?php endforeach; ?>
											<?php endif; ?>
										</select>
									</div>
									<div class="form-group">
										<label>Title <span class="text-danger">*</span></label>
										<input type="text" name="title" class="form-control input-sm" required placeholder="e.g. Administer insulin to Bed 3">
									</div>
									<div class="form-group">
										<label>Description</label>
										<textarea name="description" class="form-control input-sm" rows="2" placeholder="Details, dosage, special instructions..."></textarea>
									</div>
									<div class="form-group">
										<label>Priority</label>
										<select name="priority" class="form-control input-sm">
											<option value="NORMAL">Normal</option>
											<option value="HIGH">High</option>
											<option value="URGENT">Urgent</option>
										</select>
									</div>
									<div class="form-group">
										<label>Assign To</label>
										<select name="assigned_to" class="form-control input-sm">
											<option value="">— Unassigned —</option>
											<?php if (isset($nurses_list) && is_array($nurses_list)): ?>
												<?php foreach($nurses_list as $n): ?>
													<option value="<?php echo $n->user_id; ?>"><?php echo htmlspecialchars($n->firstname.' '.$n->lastname); ?> (<?php echo htmlspecialchars($n->username); ?>)</option>
												<?php endforeach; ?>
											<?php endif; ?>
										</select>
									</div>
									<div class="row">
										<div class="col-md-6">
											<div class="form-group">
												<label>IOP No <small class="text-muted">(optional)</small></label>
												<input type="text" name="iop_no" class="form-control input-sm">
											</div>
										</div>
										<div class="col-md-6">
											<div class="form-group">
												<label>Patient No <small class="text-muted">(optional)</small></label>
												<input type="text" name="patient_no" class="form-control input-sm">
											</div>
										</div>
									</div>
									<button type="submit" class="btn btn-primary btn-block"><i class="fa fa-plus"></i> Create Task</button>
								</form>
							</div>
						</div>
					</div>

					<!-- Right: Task Lists -->
					<div class="col-md-8">
						<!-- Open Tasks -->
						<div class="box box-warning">
							<div class="box-header with-border">
								<h3 class="box-title"><i class="fa fa-clock-o"></i> Open Tasks</h3>
								<span class="label label-warning pull-right"><?php echo count($open_tasks); ?> pending</span>
							</div>
							<div class="box-body table-responsive no-padding">
								<table class="table table-hover">
									<thead>
										<tr>
											<th>Category</th>
											<th>Task</th>
											<th>Priority</th>
											<th>Assigned To</th>
											<th>Patient</th>
											<th>Created</th>
											<th style="width:120px;"></th>
										</tr>
									</thead>
									<tbody>
										<?php if (isset($open_tasks) && is_array($open_tasks) && count($open_tasks) > 0): ?>
											<?php foreach($open_tasks as $t): ?>
												<?php
													$cat_key = isset($t->task_category) ? $t->task_category : 'OTHER';
													$cat_label = isset($task_categories[$cat_key]) ? $task_categories[$cat_key] : $cat_key;
												?>
												<tr>
													<td><span class="cat-badge cat-<?php echo $cat_key; ?>"><?php echo htmlspecialchars($cat_label); ?></span></td>
													<td>
														<strong><?php echo htmlspecialchars($t->title); ?></strong>
														<?php if (!empty($t->description)): ?>
															<div class="text-muted" style="font-size:11px;"><?php echo htmlspecialchars($t->description); ?></div>
														<?php endif; ?>
													</td>
													<td><span class="label priority-<?php echo htmlspecialchars($t->priority); ?>"><?php echo htmlspecialchars($t->priority); ?></span></td>
													<td>
														<?php if (!empty($t->assigned_firstname)): ?>
															<?php echo htmlspecialchars($t->assigned_firstname.' '.$t->assigned_lastname); ?>
														<?php else: ?>
															<span class="text-muted">—</span>
														<?php endif; ?>
													</td>
													<td>
														<?php if (!empty($t->iop_id)): ?>
															<small><?php echo htmlspecialchars($t->iop_id); ?></small>
														<?php else: ?>
															<span class="text-muted">—</span>
														<?php endif; ?>
													</td>
													<td>
														<small><?php echo date('H:i', strtotime($t->created_at)); ?></small>
														<div class="task-assignee">by <?php echo htmlspecialchars(($t->created_firstname ?: '').' '.($t->created_lastname ?: '')); ?></div>
													</td>
													<td>
														<form method="post" action="<?php echo base_url()?>app/nurse_module/complete_shift_task" style="display:inline;">
															<input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
															<input type="hidden" name="task_id" value="<?php echo $t->task_id; ?>">
															<input type="hidden" name="shift_id" value="<?php echo htmlspecialchars((string)$selected_shift_id); ?>">
															<button type="submit" class="btn btn-xs btn-success" onclick="return confirm('Mark this task as completed?');"><i class="fa fa-check"></i> Done</button>
														</form>
													</td>
												</tr>
											<?php endforeach; ?>
										<?php else: ?>
											<tr><td colspan="7" class="text-center text-muted" style="padding:25px;"><i class="fa fa-check-circle" style="font-size:24px;color:#27ae60;"></i><br>No open tasks for this shift.</td></tr>
										<?php endif; ?>
									</tbody>
								</table>
							</div>
						</div>

						<!-- Completed Tasks -->
						<div class="box box-success">
							<div class="box-header with-border">
								<h3 class="box-title"><i class="fa fa-check-circle"></i> Completed Tasks</h3>
								<span class="label label-success pull-right"><?php echo count($done_tasks); ?> done</span>
							</div>
							<div class="box-body table-responsive no-padding">
								<table class="table table-hover">
									<thead>
										<tr>
											<th>Category</th>
											<th>Task</th>
											<th>Priority</th>
											<th>Completed By</th>
											<th>Completed At</th>
											<th>Handover Notes</th>
										</tr>
									</thead>
									<tbody>
										<?php if (isset($done_tasks) && is_array($done_tasks) && count($done_tasks) > 0): ?>
											<?php foreach($done_tasks as $t): ?>
												<?php
													$cat_key = isset($t->task_category) ? $t->task_category : 'OTHER';
													$cat_label = isset($task_categories[$cat_key]) ? $task_categories[$cat_key] : $cat_key;
												?>
												<tr>
													<td><span class="cat-badge cat-<?php echo $cat_key; ?>"><?php echo htmlspecialchars($cat_label); ?></span></td>
													<td>
														<?php echo htmlspecialchars($t->title); ?>
														<?php if (!empty($t->description)): ?>
															<div class="text-muted" style="font-size:11px;"><?php echo htmlspecialchars($t->description); ?></div>
														<?php endif; ?>
													</td>
													<td><span class="label priority-<?php echo htmlspecialchars($t->priority); ?>"><?php echo htmlspecialchars($t->priority); ?></span></td>
													<td>
														<?php if (!empty($t->completed_firstname)): ?>
															<?php echo htmlspecialchars($t->completed_firstname.' '.$t->completed_lastname); ?>
														<?php else: ?>
															<span class="text-muted">—</span>
														<?php endif; ?>
													</td>
													<td><small><?php echo htmlspecialchars((string)$t->completed_at); ?></small></td>
													<td>
														<?php if (!empty($t->handover_notes)): ?>
															<small><?php echo htmlspecialchars($t->handover_notes); ?></small>
														<?php else: ?>
															<span class="text-muted">—</span>
														<?php endif; ?>
													</td>
												</tr>
											<?php endforeach; ?>
										<?php else: ?>
											<tr><td colspan="6" class="text-muted text-center">No completed tasks.</td></tr>
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
	<script src="<?php echo base_url();?>public/datepicker/js/bootstrap-datepicker.js"></script>
	<script>
		$(document).ready(function(){
			$('#shift_date').datepicker({ format: "yyyy-mm-dd", autoclose: true, todayHighlight: true });
		});
	</script>
</body>
</html>
