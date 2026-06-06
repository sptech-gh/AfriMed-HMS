<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Hebrew Medical Center — Dashboard</title>
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
                <h1>
                    Dashboard 
                    <span class="label label-success" style="margin-left: 10px; font-size: 11px; vertical-align: middle;">✓ Enhanced UI Active</span>
                </h1>
            </section>

            <section class="content">
                
                <!-- Enhanced Stats Row -->
                <div class="row" style="margin-bottom: 20px;">
                    <div class="col-lg-3 col-md-6 col-sm-6">
                        <div class="dashboard-stat stat-primary">
                            <div class="dashboard-stat-icon"><i class="fa fa-user-plus"></i></div>
                            <div class="dashboard-stat-value"><?php echo isset($latest_patient) ? count($latest_patient) : 0; ?></div>
                            <div class="dashboard-stat-label">New Patients</div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 col-sm-6">
                        <div class="dashboard-stat stat-info">
                            <div class="dashboard-stat-icon"><i class="fa fa-users"></i></div>
                            <div class="dashboard-stat-value"><?php echo isset($latest_visited_patient) ? count($latest_visited_patient) : 0; ?></div>
                            <div class="dashboard-stat-label">Visited Patients</div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 col-sm-6">
                        <div class="dashboard-stat stat-success">
                            <div class="dashboard-stat-icon"><i class="fa fa-calendar-check-o"></i></div>
                            <div class="dashboard-stat-value"><?php echo isset($getTodayAppointment) ? count($getTodayAppointment) : 0; ?></div>
                            <div class="dashboard-stat-label">Appointments</div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 col-sm-6">
                        <div class="dashboard-stat stat-info" style="margin-bottom: 10px;">
                            <div class="dashboard-stat-icon"><i class="fa fa-bed"></i></div>
                            <div class="dashboard-stat-value"><?php echo (isset($dashboard_counts) && is_array($dashboard_counts) && isset($dashboard_counts['pending_ipd'])) ? (int)$dashboard_counts['pending_ipd'] : 0; ?></div>
                            <div class="dashboard-stat-label">Active IPD (Pending)</div>
                        </div>
                        <?php if (isset($show_pending_lab_card) && $show_pending_lab_card): ?>
                        <div class="dashboard-stat stat-warning">
                            <div class="dashboard-stat-icon"><i class="fa fa-flask"></i></div>
                            <div class="dashboard-stat-value"><?php echo (isset($dashboard_counts) && is_array($dashboard_counts) && isset($dashboard_counts['pending_lab_card'])) ? (int)$dashboard_counts['pending_lab_card'] : 0; ?></div>
                            <div class="dashboard-stat-label">Pending Lab Results</div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

				<?php if (isset($show_sonography_cards) && $show_sonography_cards): ?>
				<?php $sw = (isset($sonography_weekly_stats) && is_array($sonography_weekly_stats)) ? $sonography_weekly_stats : array(); ?>
				<div class="row" style="margin-bottom: 20px;">
					<div class="col-lg-3 col-md-6 col-sm-6">
						<div class="dashboard-stat stat-success">
							<div class="dashboard-stat-icon"><i class="fa fa-check-circle"></i></div>
							<div class="dashboard-stat-value"><?php echo isset($sw['completed_this_week']) ? (int)$sw['completed_this_week'] : 0; ?></div>
							<div class="dashboard-stat-label">Sonography Completed (This Week)</div>
						</div>
					</div>
					<div class="col-lg-3 col-md-6 col-sm-6">
						<div class="dashboard-stat stat-warning">
							<div class="dashboard-stat-icon"><i class="fa fa-clock-o"></i></div>
							<div class="dashboard-stat-value"><?php echo isset($sw['overdue']) ? (int)$sw['overdue'] : 0; ?></div>
							<div class="dashboard-stat-label">Sonography Overdue</div>
						</div>
					</div>
					<div class="col-lg-3 col-md-6 col-sm-6">
						<div class="dashboard-stat stat-info">
							<div class="dashboard-stat-icon"><i class="fa fa-calendar"></i></div>
							<div class="dashboard-stat-value"><?php echo isset($sw['scheduled_today']) ? (int)$sw['scheduled_today'] : 0; ?></div>
							<div class="dashboard-stat-label">Sonography Scheduled (Today)</div>
						</div>
					</div>
					<div class="col-lg-3 col-md-6 col-sm-6">
						<div class="dashboard-stat stat-danger">
							<div class="dashboard-stat-icon"><i class="fa fa-times-circle"></i></div>
							<div class="dashboard-stat-value"><?php echo isset($sw['cancelled_this_week']) ? (int)$sw['cancelled_this_week'] : 0; ?></div>
							<div class="dashboard-stat-label">Sonography Cancelled (This Week)</div>
						</div>
					</div>
				</div>
				<?php endif; ?>

                <?php if (isset($system_health) && is_array($system_health) && isset($isSuperAdmin) && $isSuperAdmin): ?>
                <div class="row" style="margin-bottom: 20px;">
                    <section class="col-lg-12">
                        <div class="box box-primary">
                            <div class="box-header">
                                <i class="fa fa-heartbeat"></i>
                                <h3 class="box-title">System Health</h3>
                            </div>
                            <div class="box-body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <?php
                                        $dbOk = (isset($system_health['db_ok']) && $system_health['db_ok']);
                                        $lbl = $dbOk ? 'label-success' : 'label-danger';
                                        $txt = $dbOk ? 'OK' : 'FAIL';
                                        ?>
                                        <div>Database: <span class="label <?php echo $lbl; ?>" style="font-size:12px; padding:4px 8px;"><?php echo $txt; ?></span></div>
                                    </div>
                                    <div class="col-md-4">
                                        <div>Active IPD (Pending): <strong><?php echo isset($system_health['pending_ipd']) ? (int)$system_health['pending_ipd'] : 0; ?></strong></div>
                                    </div>
                                    <div class="col-md-4">
                                        <div>Pending Lab Results: <strong><?php echo isset($system_health['pending_lab']) ? (int)$system_health['pending_lab'] : 0; ?></strong></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
                <?php endif; ?>
				<?php
				$roleId = (int) $this->session->userdata('user_role');
				$roleModule = '';
				if (isset($userInfo) && isset($userInfo->module)) {
					$roleModule = strtolower(trim((string)$userInfo->module));
					if ($roleModule === 'super admin') { $roleModule = 'super_admin'; }
				}
				if ($roleModule === '') {
					switch ($roleId) {
						case 1: $roleModule = 'super_admin'; break;
						case 3: $roleModule = 'receptionist'; break;
						case 7: $roleModule = 'nurse'; break;
						case 9: $roleModule = 'it'; break;
						case 10: $roleModule = 'pharmacy'; break;
						case 11: $roleModule = 'lab'; break;
						default: $roleModule = 'general'; break;
					}
				}
				$current_role = $roleModule;
				$hms_actions = array(
					array('label' => 'Doctor OPD', 'icon' => 'fa-user-md', 'url' => base_url().'app/doctor/opd', 'roles' => array('doctor'), 'show' => true),
					array('label' => 'Doctor IPD', 'icon' => 'fa-bed', 'url' => base_url().'app/doctor/ipd', 'roles' => array('doctor'), 'show' => true),
					array('label' => 'Appointments', 'icon' => 'fa-calendar', 'url' => base_url().'app/appointment', 'roles' => array('doctor'), 'show' => !isset($hasAccesstoAppointment) || $hasAccesstoAppointment),
					array('label' => 'Add Appointment', 'icon' => 'fa-calendar-plus-o', 'url' => base_url().'app/appointment/addAppointmentList', 'roles' => array('doctor', 'receptionist', 'administrator', 'super_admin'), 'show' => !isset($hasAccesstoAddAppointment) || $hasAccesstoAddAppointment),
					array('label' => 'Register Patient', 'icon' => 'fa-user-plus', 'url' => base_url().'app/patient/addPatient', 'roles' => array('receptionist', 'administrator', 'super_admin'), 'show' => !isset($hasAccesstoAddPatient) || $hasAccesstoAddPatient),
					array('label' => 'OPD Registration', 'icon' => 'fa-stethoscope', 'url' => base_url().'app/opd/registration', 'roles' => array('receptionist', 'administrator', 'super_admin'), 'show' => !isset($hasAccesstoOPDRegistration) || $hasAccesstoOPDRegistration),
					array('label' => 'IPD Enquiry', 'icon' => 'fa-bed', 'url' => base_url().'app/ipd', 'roles' => array('administrator', 'super_admin'), 'show' => !isset($hasAccesstoIPDEnquiry) || $hasAccesstoIPDEnquiry),
					array('label' => 'Nurse Module', 'icon' => 'fa-heartbeat', 'url' => base_url().'app/nurse_module/medication', 'roles' => array('nurse'), 'show' => true),
					array('label' => 'Laboratory', 'icon' => 'fa-flask', 'url' => base_url().'app/laboratory', 'roles' => array('lab'), 'show' => true),
					array('label' => 'POS / Billing', 'icon' => 'fa-credit-card', 'url' => base_url().'app/pos', 'roles' => array('billing', 'administrator', 'super_admin'), 'show' => !isset($hasAccesstoPOS) || $hasAccesstoPOS),
					array('label' => 'Manage Users', 'icon' => 'fa-users', 'url' => base_url().'app/users', 'roles' => array('administrator', 'super_admin', 'it'), 'show' => !isset($hasAccesstoUsers) || $hasAccesstoUsers),
					array('label' => 'System Parameters', 'icon' => 'fa-sliders', 'url' => base_url().'app/parameters', 'roles' => array('administrator', 'super_admin', 'it'), 'show' => !isset($hasAccesstoAdminParameters) || $hasAccesstoAdminParameters)
				);
				?>

				<div class="row">
					<section class="col-lg-12">
						<div class="box box-primary hms-workspace">
							<div class="box-header">
								<i class="fa fa-th-large"></i>
								<h3 class="box-title">My Workspace</h3>
							</div>
							<div class="box-body">
								<div class="hms-action-grid">
								<?php $hms_actions = (isset($hms_actions) && is_array($hms_actions)) ? $hms_actions : array(); ?>
								<?php foreach($hms_actions as $a): ?>
									<?php if ((!isset($a['roles']) || in_array($current_role, $a['roles'])) && (!isset($a['show']) || $a['show'])): ?>
										<a class="hms-action-tile" href="<?php echo $a['url']; ?>">
											<span class="hms-action-icon"><i class="fa <?php echo $a['icon']; ?>"></i></span>
											<span class="hms-action-label"><?php echo $a['label']; ?></span>
										</a>
									<?php endif; ?>
								<?php endforeach; ?>
							</div>
							</div>
						</div>
					</section>
				</div>

				<?php
				$allowed_stats_roles = array('doctor','lab','nurse','receptionist','administrator','super_admin','it','billing');
				?>

				<div class="row">
					<?php if (isset($current_role) && $current_role === 'doctor'): ?>
					<section class="col-lg-8">
						<div class="box box-primary">
							<div class="box-header">
								<i class="fa fa-user-md"></i>
								<h3 class="box-title">Doctor: My Queue</h3>
								<div class="pull-right box-tools">
									<a class="btn btn-default btn-sm" href="<?php echo base_url().'app/doctor/opd'; ?>"><i class="fa fa-stethoscope"></i> OPD</a>
									<a class="btn btn-default btn-sm" href="<?php echo base_url().'app/doctor/ipd'; ?>"><i class="fa fa-bed"></i> IPD</a>
									<a class="btn btn-default btn-sm" href="<?php echo base_url().'app/doctor_messages/inbox'; ?>"><i class="fa fa-envelope"></i> Messages
										<?php if (isset($doctor_message_unread_count) && (int)$doctor_message_unread_count > 0): ?>
											<span class="badge" style="margin-left:4px;"><?php echo (int)$doctor_message_unread_count; ?></span>
										<?php endif; ?>
									</a>
									<a class="btn btn-primary btn-sm" href="<?php echo base_url().'app/doctor_transfer/inbox'; ?>"><i class="fa fa-exchange"></i> Transfers
										<?php if (isset($doctor_transfer_incoming_pending) && (int)$doctor_transfer_incoming_pending > 0): ?>
											<span class="badge" style="margin-left:4px;"><?php echo (int)$doctor_transfer_incoming_pending; ?></span>
										<?php endif; ?>
									</a>
								</div>
							</div>
							<div class="box-body">
								<div class="row">
									<div class="col-md-6">
										<h4 style="margin-top:0;">OPD (Today)</h4>
										<div class="table-responsive">
											<table class="table table-hover table-striped">
												<thead>
													<tr>
														<th>OPD No</th>
														<th>Patient</th>
														<th>Time</th>
													</tr>
												</thead>
												<tbody>
													<?php if (isset($doctor_opd_queue) && is_array($doctor_opd_queue) && count($doctor_opd_queue) > 0): ?>
														<?php foreach($doctor_opd_queue as $p): ?>
														<tr>
															<td><?php echo $p->IO_ID; ?></td>
															<td><?php echo $p->name; ?></td>
															<td><?php echo date('H:i', strtotime($p->time_visit)); ?></td>
														</tr>
														<?php endforeach; ?>
													<?php else: ?>
														<tr><td colspan="3" class="text-muted">No OPD patients in your queue.</td></tr>
													<?php endif; ?>
												</tbody>
											</table>
										</div>
									</div>
									<div class="col-md-6">
										<h4 style="margin-top:0;">IPD (Today)</h4>
										<div class="table-responsive">
											<table class="table table-hover table-striped">
												<thead>
													<tr>
														<th>IPD No</th>
														<th>Patient</th>
														<th>Bed</th>
													</tr>
												</thead>
												<tbody>
													<?php if (isset($doctor_ipd_queue) && is_array($doctor_ipd_queue) && count($doctor_ipd_queue) > 0): ?>
														<?php foreach($doctor_ipd_queue as $p): ?>
														<tr>
															<td><?php echo $p->IO_ID; ?></td>
															<td><?php echo $p->name; ?></td>
															<td><?php echo isset($p->bed_name) ? $p->bed_name : ''; ?></td>
														</tr>
														<?php endforeach; ?>
													<?php else: ?>
														<tr><td colspan="3" class="text-muted">No IPD patients in your queue.</td></tr>
													<?php endif; ?>
												</tbody>
											</table>
										</div>
									</div>
								</div>
							</div>
						</div>
					</section>
					<section class="col-lg-4">
						<div class="box box-primary">
							<div class="box-header">
								<i class="fa fa-toggle-on"></i>
								<h3 class="box-title">Doctor: Availability</h3>
							</div>
							<div class="box-body">
								<?php
								$docStatus = isset($userInfo->doctorIsIn) ? strtoupper(trim((string)$userInfo->doctorIsIn)) : '';
								if ($docStatus !== 'IN' && $docStatus !== 'OUT') { $docStatus = 'UNKNOWN'; }
								$rawLastIn = isset($userInfo->doctorLastIn) ? (string)$userInfo->doctorLastIn : '';
								$rawLastOut = isset($userInfo->doctorLastOut) ? (string)$userInfo->doctorLastOut : '';
								$docLastIn = ($rawLastIn && $rawLastIn !== '0000-00-00 00:00:00') ? date('M d, Y H:i', strtotime($rawLastIn)) : '—';
								$docLastOut = ($rawLastOut && $rawLastOut !== '0000-00-00 00:00:00') ? date('M d, Y H:i', strtotime($rawLastOut)) : '—';
								$docLabelClass = ($docStatus === 'IN') ? 'label-success' : (($docStatus === 'OUT') ? 'label-danger' : 'label-default');
								?>
								<div style="margin-bottom:10px;">
									<span class="label <?php echo $docLabelClass; ?>" style="font-size:12px; padding:6px 10px;">Status: <?php echo $docStatus; ?></span>
								</div>
								<div class="text-muted" style="font-size:13px; margin-bottom:12px;">
									<div>Last IN: <strong><?php echo $docLastIn; ?></strong></div>
									<div>Last OUT: <strong><?php echo $docLastOut; ?></strong></div>
								</div>
								<div>
									<a class="btn btn-success btn-sm" href="<?php echo base_url().'general/procDocAvail/'.$this->session->userdata('user_id').'/IN'; ?>">Set IN</a>
									<a class="btn btn-danger btn-sm" href="<?php echo base_url().'general/procDocAvail/'.$this->session->userdata('user_id').'/OUT'; ?>">Set OUT</a>
								</div>
							</div>
						</div>

						<div class="box box-primary">
							<div class="box-header">
								<i class="fa fa-calendar"></i>
								<h3 class="box-title">Doctor: My Appointments</h3>
							</div>
							<div class="box-body no-padding">
								<div class="table-responsive">
									<table class="table table-hover">
										<thead>
											<tr><th>Patient</th><th>Time</th></tr>
										</thead>
										<tbody>
											<?php if (isset($doctor_today_appointments) && is_array($doctor_today_appointments) && count($doctor_today_appointments) > 0): ?>
												<?php foreach($doctor_today_appointments as $a): ?>
												<tr>
													<td><?php echo $a->name; ?></td>
													<td><?php echo $a->appHour.':'.$a->appMinutes.' '.$a->appAMPM; ?></td>
												</tr>
												<?php endforeach; ?>
											<?php else: ?>
												<tr><td colspan="2" class="text-muted">No appointments assigned to you today.</td></tr>
											<?php endif; ?>
										</tbody>
									</table>
								</div>
							</div>
						</div>
					</section>
					<?php endif; ?>

					<?php if (isset($current_role) && in_array($current_role, array('administrator','super_admin','it','nurse'))): ?>
						<?php if (in_array($current_role, array('administrator','super_admin','it'))): ?>
						<section class="col-lg-6">
							<div class="box box-primary">
								<div class="box-header">
									<i class="fa fa-cogs"></i>
									<h3 class="box-title">Admin: System Shortcuts</h3>
								</div>
								<div class="box-body">
									<a class="btn btn-primary btn-block" href="<?php echo base_url().'app/users'; ?>">Manage Users</a>
									<a class="btn btn-default btn-block" href="<?php echo base_url().'app/parameters'; ?>">System Parameters</a>
									<a class="btn btn-default btn-block" href="<?php echo base_url().'app/pages'; ?>">System Pages</a>
									<a class="btn btn-default btn-block" href="<?php echo base_url().'app/backup'; ?>">Backup Database</a>
								</div>
							</div>
						</section>
						<?php endif; ?>

						<?php if ($current_role === 'nurse'): ?>
						<section class="col-lg-6">
							<div class="box box-primary">
								<div class="box-header">
									<i class="fa fa-heartbeat"></i>
									<h3 class="box-title">Nurse: Quick Tasks</h3>
								</div>
								<div class="box-body">
									<a class="btn btn-primary btn-block" href="<?php echo base_url().'app/nurse_module/medication'; ?>">Patient Medication</a>
									<a class="btn btn-default btn-block" href="<?php echo base_url().'app/nurse_module/intake_output'; ?>">Intake / Output</a>
									<a class="btn btn-default btn-block" href="<?php echo base_url().'app/nurse_module/vitalSign'; ?>">Vital Signs</a>
								</div>
							</div>
						</section>
						<?php endif; ?>
					<?php endif; ?>
				</div>

				<?php if (isset($current_role) && $current_role === 'lab'): ?>
				<div class="row">
					<section class="col-lg-12">
						<div class="box box-primary">
							<div class="box-header">
								<i class="fa fa-flask"></i>
								<h3 class="box-title">Laboratory: Pending Requests</h3>
								<span class="label label-warning" style="margin-left:8px;"><?php echo isset($lab_pending_count) ? (int)$lab_pending_count : 0; ?></span>
								<div class="pull-right box-tools">
									<a class="btn btn-default btn-sm" href="<?php echo base_url().'app/laboratory'; ?>">Open Lab</a>
								</div>
							</div>
							<div class="box-body no-padding">
								<div class="table-responsive">
									<table class="table table-hover">
										<thead>
											<tr><th>IOP</th><th>Patient</th><th>Date</th></tr>
										</thead>
										<tbody>
											<?php if (isset($lab_pending_requests) && is_array($lab_pending_requests) && count($lab_pending_requests) > 0): ?>
												<?php foreach($lab_pending_requests as $r): ?>
												<tr>
													<td><?php echo $r->iop_id; ?></td>
													<td><?php echo $r->patient_name; ?></td>
													<td><?php echo date('M d, Y', strtotime($r->dDate)); ?></td>
												</tr>
												<?php endforeach; ?>
											<?php else: ?>
												<tr><td colspan="3" class="text-muted">No pending lab requests.</td></tr>
											<?php endif; ?>
										</tbody>
									</table>
								</div>
							</div>
						</div>
					</section>
				</div>
				<?php endif; ?>

				<?php if (isset($current_role) && $current_role === 'receptionist'): ?>
				<div class="row">
					<section class="col-lg-12">
						<div class="box box-primary">
							<div class="box-header">
								<div class="pull-right box-tools">
									<button class="btn btn-primary btn-sm" data-widget='collapse' data-toggle="tooltip" title="Collapse">
										<i class="fa fa-minus"></i>
									</button>
								</div>
								<i class="fa fa-calendar"></i>
								<h3 class="box-title">Today's Patient Appointments</h3>
							</div>
							<div class="box-body no-padding">
								<div class="table-responsive">
									<table class="table table-hover">
										<thead>
											<tr>
												<th>Patient No.</th>
												<th>Patient Name</th>
												<th>Appointment Date</th>
												<th>Consultant Doctor</th>
												<th>Entry Date</th>
												<th>Remarks</th>
											</tr>
										</thead>
										<tbody>
											<?php if(isset($getTodayAppointment) && count($getTodayAppointment) > 0): ?>
												<?php foreach($getTodayAppointment as $appointment): ?>
												<tr>
													<td><a href="patient/view/<?php echo $appointment->patient_no?>"><?php echo $appointment->patient_no?></a></td>
													<td><?php echo $appointment->name?></td>
													<td><?php echo date("M d, Y", strtotime($appointment->appointmentDate))." ".$appointment->appHour.":".$appointment->appMinutes." ".$appointment->appAMPM;?></td>
													<td><?php echo $appointment->consultantDoctor?></td>
													<td><?php echo date("M d, Y", strtotime($appointment->dateEntry));?></td>
													<td><?php echo $appointment->appointmentReason?></td>
												</tr>
												<?php endforeach; ?>
											<?php else: ?>
												<tr>
													<td colspan="6" class="text-center text-muted">No appointments today</td>
												</tr>
											<?php endif; ?>
										</tbody>
									</table>
								</div>
							</div>
							<div class="box-footer"></div>
						</div>
					</section>
				</div>
				<div class="row">
					<section class="col-lg-12">
						<div class="box box-primary">
							<div class="box-header">
								<i class="fa fa-calendar"></i>
								<h3 class="box-title">Visited Patients</h3>
							</div>
							<div class="box-body no-padding">
								<div class="table-responsive">
									<table class="table table-hover">
										<thead>
											<tr>
												<th>OPD No.</th>
												<th>Patient Name</th>
												<th>Date</th>
												<th>Department</th>
											</tr>
										</thead>
										<tbody>
											<?php if(isset($latest_visited_patient) && count($latest_visited_patient) > 0): ?>
												<?php foreach($latest_visited_patient as $visited): ?>
												<tr>
													<td><?php echo $visited->IO_ID?></td>
													<td><?php echo $visited->patient?></td>
													<td><?php echo date("M d, Y h:i:s", strtotime($visited->date_visit));?></td>
													<td><?php echo isset($visited->dept_name) ? $visited->dept_name : (isset($visited->department) ? $visited->department : ''); ?></td>
												</tr>
												<?php endforeach; ?>
											<?php else: ?>
												<tr>
													<td colspan="4" class="text-center text-muted">No visited patients</td>
												</tr>
											<?php endif; ?>
										</tbody>
									</table>
								</div>
							</div>
							<div class="box-footer"></div>
						</div>
					</section>
				</div>
				<?php endif; ?>

            </section>
        </aside>
    </div>

    <script src="<?php echo base_url(); ?>public/js/jquery.min.js"></script>
    <script src="<?php echo base_url(); ?>public/js/bootstrap.min.js" type="text/javascript"></script>
    <script src="<?php echo base_url(); ?>public/js/AdminLTE/app.js" type="text/javascript"></script>
    <script src="<?php echo base_url(); ?>public/js/hms-enhanced.js?v=<?php echo time(); ?>"></script>
</body>
</html>
