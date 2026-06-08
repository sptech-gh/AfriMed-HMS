<!DOCTYPE html>
<html>
<head>
        <meta charset="UTF-8">
        <title>Hebrew Medical Center</title>
        <meta content="width=device-width, initial-scale=1.0" name="viewport">
        <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">

        <link href="<?php echo base_url() ?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
        <link href="<?php echo base_url(); ?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
        <link href="<?php echo base_url(); ?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
        <link href="<?php echo base_url(); ?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
        <link rel="stylesheet" href="<?php echo base_url(); ?>public/datepicker/css/datepicker.css">

        <!--[if lt IE 9]>
          <script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>
          <script src="https://oss.maxcdn.com/libs/respond.js/1.3.0/respond.min.js"></script>
        <![endif]-->

        <style>
        /* ---- OPD Dashboard ---- */
        .opd-row-clickable { cursor: pointer; }
        .opd-row-clickable:hover td { background-color: #e8f4fd !important; }
        .opd-row-clickable td { transition: background-color 0.15s ease; }

        .opd-search-box { border-top: 3px solid #3c8dbc; margin-bottom: 0; }
        .opd-search-box .box-header { background: #f8f9fa; padding: 12px 15px; }
        .opd-search-box .box-header .box-title { font-size: 14px; font-weight: 600; color: #333; }
        .opd-search-box .box-body { padding: 15px; }
        .opd-search-box .filter-row { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; }
        .opd-search-box .filter-group { display: flex; flex-direction: column; }
        .opd-search-box .filter-group label { font-size: 11px; font-weight: 600; color: #666; text-transform: uppercase; letter-spacing: 0.3px; margin-bottom: 4px; }
        .opd-search-box .filter-group .form-control { height: 34px; font-size: 13px; }
        .opd-search-box .filter-group select.form-control { min-width: 160px; }
        .opd-search-box .btn-search { height: 34px; padding: 0 18px; font-size: 13px; }

        /* Select2 in flex rows: container does not inherit select min-width */
        .opd-search-box .filter-group { min-width: 160px; }
        .opd-search-box .filter-group .select2-container { min-width: 160px !important; width: 100% !important; }
        .opd-search-box .filter-group .select2-selection { height: 34px !important; }
        .opd-search-box .filter-group .select2-selection__rendered { line-height: 32px !important; }
        .opd-search-box .filter-group .select2-selection__arrow { height: 32px !important; }

        .opd-results-box { border: none; box-shadow: 0 1px 3px rgba(0,0,0,0.08); margin-bottom: 15px; }
        .opd-results-box .box-header { background: #fff; border-bottom: 1px solid #eee; padding: 10px 15px; }
        .opd-results-box .box-header .box-title { font-size: 14px; font-weight: 600; color: #333; }
        .opd-results-box .table > thead > tr > th { background: #f5f6f8; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.4px; color: #555; padding: 10px 12px; border-bottom: 2px solid #ddd; white-space: nowrap; }
        .opd-results-box .table > tbody > tr > td { padding: 10px 12px; vertical-align: middle; font-size: 13px; }
        .opd-results-box .table > tbody > tr:last-child > td { border-bottom: none; }

        #live-queue-box { box-shadow: 0 1px 3px rgba(0,0,0,0.08); margin-bottom: 15px; }
        #live-queue-box .box-header { padding: 10px 15px; }
        #live-queue-box .table > thead > tr > th { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.4px; color: #555; padding: 10px 12px; background: #f0f7fc; white-space: nowrap; }
        #live-queue-box .table > tbody > tr > td { padding: 10px 12px; vertical-align: middle; font-size: 13px; }

        .visited-box { box-shadow: 0 1px 3px rgba(0,0,0,0.08); margin-bottom: 15px; }
        .visited-box .box-header { padding: 10px 15px; background: #f8f9fa; }
        .visited-box .box-header .box-title { font-size: 14px; font-weight: 600; color: #333; }
        .visited-box .table > thead > tr > th { background: #f5f6f8; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.4px; color: #555; padding: 10px 12px; border-bottom: 2px solid #ddd; white-space: nowrap; }
        .visited-box .table > tbody > tr > td { padding: 10px 12px; vertical-align: middle; font-size: 13px; }

        .label { font-size: 11px; font-weight: 600; padding: 4px 8px; border-radius: 3px; }
        .badge { font-size: 11px; padding: 3px 7px; }
        .btn-xs { font-size: 11px; padding: 3px 8px; border-radius: 3px; }
        .content-header h1 { font-size: 20px; font-weight: 600; }

        .opd-content-header .opd-content-header-row { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; width: 100%; }
        .opd-content-header .opd-content-header-row h1 { margin: 0; }
        .opd-content-header .opd-content-header-actions { flex: 0 0 auto; }

        /* AdminLTE floats breadcrumbs with a more specific selector; override with equal specificity */
        .right-side > .content-header.opd-content-header > .breadcrumb {
            float: none;
            clear: both;
            position: static;
            margin-top: 8px;
        }

		.opd-tabs-custom { border-top: 3px solid #3c8dbc; box-shadow: 0 1px 3px rgba(0,0,0,0.08); margin-bottom: 15px; background: var(--hms-surface, #fff) !important; }
		.opd-tabs-custom > .nav-tabs > li > a { font-weight: 600; font-size: 13px; padding: 10px 14px; }
		.opd-tabs-custom > .nav-tabs > li.active > a { border-top-color: transparent; }
		.opd-tabs-custom > .tab-content { padding: 0; background: var(--hms-surface, #fff) !important; }
		.opd-tab-meta { padding: 10px 15px; border-bottom: 1px solid var(--hms-border, #eee); background: var(--hms-surface, #fff); display: flex; align-items: center; justify-content: space-between; gap: 10px; }
		.opd-tab-meta .text-muted { font-size: 11px; }
		.opd-tab-body { background: var(--hms-surface, #fff) !important; }
		.opd-tab-body.table-responsive { border: 1px solid var(--hms-border, #ddd) !important; }
		.opd-tab-body table,
		.opd-tab-body .table,
		.opd-tab-body table tbody,
		.opd-tab-body .table tbody,
		.opd-tab-body table thead,
		.opd-tab-body .table thead,
		.opd-tab-body table tr,
		.opd-tab-body .table tr,
		.opd-tab-body table td,
		.opd-tab-body .table td { background: transparent !important; background-color: transparent !important; }
		.opd-tab-body .table > thead > tr > th { background: var(--table-head-bg, #f5f6f8) !important; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.4px; color: var(--table-head-text-color, #555) !important; padding: 10px 12px; border-bottom: 2px solid var(--table-border-color, #ddd) !important; white-space: nowrap; }
		.opd-tab-body .table > tbody > tr > td { padding: 10px 12px; vertical-align: middle; font-size: 13px; }

        @media (max-width: 992px) {
            .opd-search-box .filter-row { gap: 8px; }
            .opd-search-box .filter-group select.form-control { min-width: 130px; }
        }
        </style>
</head>

<body class="skin-blue">
    <!-- header logo: style can be found in header.less -->
    <?php require_once(APPPATH . 'views/include/header.php'); ?>

    <div class="wrapper row-offcanvas row-offcanvas-left">

        <?php require_once(APPPATH . 'views/include/sidebar.php'); ?>

        <!-- Right side column. Contains the navbar and content of the page -->
        <aside class="right-side">
            <!-- Content Header (Page header) -->
            <section class="content-header opd-content-header">
                <div class="opd-content-header-row">
                    <h1><?php echo (isset($is_doctor_view) && $is_doctor_view) ? 'My OPD Patients' : 'Out-Patient Master'; ?></h1>
                    <?php if (isset($can_opd_closure_desk) && $can_opd_closure_desk): ?>
                        <div class="opd-content-header-actions">
                            <a class="btn btn-default btn-sm" href="<?php echo base_url(); ?>app/opd/closure_desk">
                                <i class="fa fa-lock"></i> 24hr Closure Desk
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                <ol class="breadcrumb">
                    <li><a href="<?php echo base_url() ?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                    <li><a href="#">Patient Management</a></li>
                    <li><a href="#">OPD</a></li>
                    <li class="active"><?php echo (isset($is_doctor_view) && $is_doctor_view) ? 'My OPD Patients' : 'Out-Patient Master'; ?></li>
                </ol>
            </section>

            <!-- Main content -->
            <section class="content">


                <div class="row">
<?php if (!isset($is_doctor_view) || !$is_doctor_view): ?>
                    <div class="col-md-12">
                        <div class="box opd-search-box">
                            <div class="box-header">
                                <i class="fa fa-filter"></i>
                                <h3 class="box-title">Search OPD Patients</h3>
                            </div>
                            <div class="box-body">
                                <form method="post" action="">
                                    <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                                    <?php
                                    $opdFrom = $this->session->userdata('search_opd_From');
                                    $opdTo = $this->session->userdata('search_opd_cTo');
                                    $opdDept = $this->session->userdata('search_opd_department');
                                    $opdDoctor = $this->session->userdata('search_opd_doctor');
                                    $opdIns = $this->session->userdata('search_opd_insurance');
                                    $opdSearch = $this->session->userdata('search_opd_master');
                                    if ($opdFrom === null || trim((string)$opdFrom) === '') { $opdFrom = date('Y-m-d'); }
                                    if ($opdTo === null || trim((string)$opdTo) === '') { $opdTo = date('Y-m-d'); }
                                    ?>
                                    <div class="filter-row">
                                        <div class="filter-group">
                                            <label>From Date</label>
                                            <input class="form-control" name="cFrom" id="cFrom" type="text" value="<?php echo $opdFrom; ?>" placeholder="YYYY-MM-DD" style="width:130px;" required>
                                        </div>
                                        <div class="filter-group">
                                            <label>To Date</label>
                                            <input class="form-control" name="cTo" id="cTo" type="text" value="<?php echo $opdTo; ?>" placeholder="YYYY-MM-DD" style="width:130px;" required>
                                        </div>
                                        <div class="filter-group">
                                            <label>Department</label>
                                            <select name="department" id="department" class="form-control select2">
                                                <option value="">All Departments</option>
                                                <?php foreach ($departmentList as $departmentList) {
                                                    $selected = ((string)$opdDept === (string)$departmentList->department_id) ? "selected='selected'" : "";
                                                ?>
                                                    <option value="<?php echo $departmentList->department_id; ?>" <?php echo $selected; ?>><?php echo $departmentList->dept_name; ?></option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                        <div class="filter-group">
                                            <label>Consultant Doctor</label>
                                            <select name="doctor" id="doctor" class="form-control">
                                                <option value="">All Doctors</option>
                                                <?php foreach ($doctorList as $doctorList) {
                                                    $selected = ((string)$opdDoctor === (string)$doctorList->user_id) ? "selected='selected'" : "";
                                                ?>
                                                    <option value="<?php echo $doctorList->user_id; ?>" <?php echo $selected; ?>><?php echo $doctorList->name; ?></option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                        <div class="filter-group">
                                            <label>Coverage</label>
                                            <select name="insurance" id="insurance" class="form-control">
                                                <option value="">All Coverage</option>
                                                <?php foreach ($insuranceCompList as $insuranceCompList) {
                                                    $selected = ((string)$opdIns === (string)$insuranceCompList->in_com_id) ? "selected='selected'" : "";
                                                ?>
                                                    <option value="<?php echo $insuranceCompList->in_com_id; ?>" <?php echo $selected; ?>><?php echo $insuranceCompList->company_name; ?></option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                        <div class="filter-group">
                                            <label>Quick Search</label>
                                            <input type="text" class="form-control" name="search" id="search" placeholder="OPD / Name" style="width:160px;" value="<?php echo (string)$opdSearch; ?>">
                                        </div>
                                        <div class="filter-group">
                                            <label>&nbsp;</label>
                                            <button class="btn btn-primary btn-search" name="btnSearch" id="btnSearch" type="submit"><i class="fa fa-search"></i> Search</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
<?php endif; ?>

					<div class="col-md-12">
						<div class="nav-tabs-custom opd-tabs-custom">
							<ul class="nav nav-tabs">
								<li class="active"><a href="#opd_tab_active" data-toggle="tab"><i class="fa fa-stethoscope"></i> <?php echo (isset($is_doctor_view) && $is_doctor_view) ? 'Active' : 'Active OPD'; ?> <?php echo isset($active_count) ? '<span class="badge bg-light-blue">' . (int)$active_count . '</span>' : ''; ?></a></li>
								<li><a href="#opd_tab_queue" data-toggle="tab"><i class="fa fa-users"></i> Live Queue <span class="badge bg-light-blue" id="live-queue-count">&#8230;</span></a></li>
								<li><a href="#opd_tab_visited" data-toggle="tab"><i class="fa fa-check-circle"></i> <?php echo (isset($is_doctor_view) && $is_doctor_view) ? 'Completed' : 'Visited'; ?> <?php echo isset($visited_count) ? '<span class="badge bg-green">' . (int)$visited_count . '</span>' : ''; ?></a></li>
							</ul>
							<div class="tab-content">
								<div class="tab-pane active" id="opd_tab_active">
									<div class="opd-tab-meta">
										<div><strong><?php echo (isset($is_doctor_view) && $is_doctor_view) ? 'Today\'s Active Patients' : 'Active OPD Patients'; ?></strong></div>
										<div class="text-muted"><?php echo date('l, F j, Y'); ?></div>
									</div>
									<div class="opd-tab-body table-responsive no-padding">
										<?php echo $message; ?>
										<?php echo $this->session->flashdata('nhis_warning') ? $this->session->flashdata('nhis_warning') : ''; ?>
										<?php echo $this->session->flashdata('nhis_billing_info') ? $this->session->flashdata('nhis_billing_info') : ''; ?>
										<?php echo $table; ?>
									</div>
								</div>
								<div class="tab-pane" id="opd_tab_queue">
									<div class="opd-tab-meta">
										<div><strong>Live OPD Queue</strong></div>
										<span class="text-muted" id="queue-last-updated" style="display:none;"></span>
									</div>
									<div id="live-queue-notification" style="display:none;margin:10px;" class="alert alert-success alert-dismissable">
										<button type="button" class="close" data-dismiss="alert">&times;</button>
										<i class="fa fa-arrow-right"></i> <strong>Next patient called:</strong> <span id="promoted-patient-name"></span>
									</div>
									<div class="opd-tab-body table-responsive no-padding">
										<table class="table table-hover table-condensed" style="margin-bottom:0;">
											<thead><tr>
												<th>#</th><th>OPD No.</th><th>Patient</th><th>Doctor</th><th>Time</th><th>Status</th>
											</tr></thead>
											<tbody id="live-queue-body">
												<tr><td colspan="6" class="text-center text-muted" style="padding:20px;"><i class="fa fa-spinner fa-spin"></i> Loading queue&#8230;</td></tr>
											</tbody>
										</table>
									</div>
								</div>
								<div class="tab-pane" id="opd_tab_visited">
									<div class="opd-tab-meta">
										<div><strong><?php echo (isset($is_doctor_view) && $is_doctor_view) ? 'Completed Today' : 'Visited Patients'; ?></strong></div>
										<div class="text-muted"><?php echo isset($visited_count) ? ((int)$visited_count . ' total') : ''; ?></div>
									</div>
									<div class="opd-tab-body table-responsive no-padding">
										<?php echo isset($table_visited) ? $table_visited : '<p class="text-center text-muted" style="padding:20px;">No visited patients for this period.</p>'; ?>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
                








            </section><!-- /.content -->
        </aside><!-- /.right-side -->
    </div><!-- ./wrapper -->


    <!-- jQuery is loaded globally in include/header.php -->
    <script src="<?php echo base_url(); ?>public/js/bootstrap.min.js" type="text/javascript"></script>
    <script src="<?php echo base_url(); ?>public/js/AdminLTE/app.js" type="text/javascript"></script>

    <!-- BDAY -->
    <script src="<?php echo base_url(); ?>public/datepicker/js/bootstrap-datepicker.js"></script>
    <script type="text/javascript">
        // When the document is ready
        $(document).ready(function() {

            $('#cFrom').datepicker({
                //format: "dd/mm/yyyy"
                format: "yyyy-mm-dd"
            });

            $('#cTo').datepicker({
                //format: "dd/mm/yyyy"
                format: "yyyy-mm-dd"
            });

        });


        $(document).ready(function() {
            if ($.fn && $.fn.select2) {
                $('select').select2({ width: '100%' });
            }
        });

		// Whole-row click — navigate to the OPD visit view unless the click was on an interactive element
		$(document).on('click', '.opd-row-clickable', function(e) {
			var $target = $(e.target);
			// Ignore clicks on links, buttons, inputs, selects, and any of their descendants
			if ($target.closest('a, button, input, select, textarea, [data-toggle], .dropdown').length > 0) {
				return;
			}
			var href = $(this).data('href');
			if (href) {
				window.location.href = href;
			}
		});

		$(document).on('click', '.opd-reassign', function() {
			$('#reassign_iop_id').val($(this).data('iop-id'));
			$('#reassign_patient_no').val($(this).data('patient-no'));
		});

		// Live Queue polling
		var _queueStatusMap = {};
		var _queueBadgeClass = {
			'IN_CONSULTATION':    'label-warning',
			'WAITING':            'label-info',
			'LAB_PENDING':        'label-danger',
			'LAB_COMPLETED':      'label-default',
			'PHARMACY_PENDING':   'label-primary',
			'PHARMACY_COMPLETED': 'label-default'
		};
		var _queueLabel = {
			'IN_CONSULTATION':    'In Consultation',
			'WAITING':            'Waiting',
			'LAB_PENDING':        'Lab Pending',
			'LAB_COMPLETED':      'Lab Done',
			'PHARMACY_PENDING':   'Pharmacy Pending',
			'PHARMACY_COMPLETED': 'Pharmacy Done'
		};

		function refreshLiveQueue() {
			$.getJSON('<?php echo base_url(); ?>app/opd/queue_status_ajax', function(data) {
				if (!data.ok) return;
				var rows = data.queue;
				$('#live-queue-count').text(rows.length);
				if ($('#opd_tab_queue').hasClass('active')) {
					$('#queue-last-updated').text('Updated ' + new Date().toLocaleTimeString()).show();
				}
				if (rows.length === 0) {
					$('#live-queue-body').html('<tr><td colspan="6" class="text-center text-muted">No active patients in queue</td></tr>');
					_queueStatusMap = {};
					return;
				}
				var promoted = [];
				var html = '';
				$.each(rows, function(i, r) {
					var prev = _queueStatusMap[r.iop_id];
					if (prev && prev !== r.status && r.status === 'IN_CONSULTATION') {
						promoted.push(r.patient_name + ' (' + r.iop_id + ')');
					}
					_queueStatusMap[r.iop_id] = r.status;
					var cls   = _queueBadgeClass[r.status] || 'label-default';
					var lbl   = _queueLabel[r.status]    || r.status;
					// Optional payment gate badge from cache snapshot
					var gateBadgeHtml = '';
					if (r.gate && r.gate.ok && r.gate.status && r.gate.status !== 'UNKNOWN') {
						var gCls = r.gate.badge_class || 'label-default';
						var gLbl = r.gate.label || 'Gate';
						var gTip = r.gate.tooltip || '';
						gateBadgeHtml = ' <span class="label '+ $('<span>').text(gCls).html() +'"'
							+ (gTip ? ' title="'+ $('<span>').text(gTip).html() +'"' : '')
							+ ' style="font-size:10px;">'
							+ '<i class="fa '+ (r.gate.can_start ? 'fa-unlock' : 'fa-lock') +'"></i> '
							+ $('<span>').text(gLbl).html()
							+ '</span>';
					}
					var rowCls = (r.status === 'IN_CONSULTATION') ? ' style="background:#fffde7;"' : '';
					var iop_safe = r.iop_id.replace(/ /g, '-');
					var pno_safe = r.patient_no.replace(/ /g, '-');
					html += '<tr'+rowCls+'>';
					html += '<td>'+(i+1)+'</td>';
					html += '<td><a href="<?php echo base_url(); ?>app/opd/view/'+iop_safe+'/'+pno_safe+'">'+$('<span>').text(r.iop_id).html()+'</a></td>';
					html += '<td>'+$('<span>').text(r.patient_name).html()+'</td>';
					html += '<td>'+$('<span>').text(r.doctor_name).html()+'</td>';
					html += '<td>'+$('<span>').text(r.time_visit).html()+'</td>';
					html += '<td><span class="label '+cls+'">'+lbl+'</span>'+gateBadgeHtml+'</td>';
					html += '</tr>';
				});
				$('#live-queue-body').html(html);
				if (promoted.length > 0) {
					$('#promoted-patient-name').text(promoted.join(', '));
					$('#live-queue-notification').fadeIn().delay(6000).fadeOut();
				}
				// Disable start consultation buttons for busy doctors
				var busyDoctors = data.busy_doctors || [];
				$('.start-consultation-btn').each(function() {
					var doctorId = $(this).data('doctor-id');
					if (busyDoctors.indexOf(doctorId) !== -1) {
						$(this).prop('disabled', true).html('<i class="fa fa-clock-o"></i> Doctor Busy')
							.removeClass('btn-warning start-consultation-btn')
							.addClass('btn-default')
							.css({'cursor':'not-allowed','opacity':'0.6'})
							.attr('title', 'Doctor is currently busy');
					}
				});
			});
		}
		refreshLiveQueue();
		setInterval(refreshLiveQueue, 15000);

		// Only show "Updated" meta when viewing Live Queue tab
		$('a[href="#opd_tab_queue"]').on('shown.bs.tab', function() {
			$('#queue-last-updated').show();
			refreshLiveQueue();
		});
		$('a[href="#opd_tab_active"], a[href="#opd_tab_visited"]').on('shown.bs.tab', function() {
			$('#queue-last-updated').hide();
		});

		// Start Consultation Button - AJAX (replaces old anchor link)
		$(document).on('click', '.start-consultation-btn', function() {
			var $btn = $(this);
			if ($btn.prop('disabled')) return;

			var iop_id     = $btn.data('iop');
			var patient_no = $btn.data('patient');

			if (!confirm('Start consultation for this patient?')) return;

			$btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Starting...');

			$.ajax({
				url: '<?php echo base_url(); ?>app/opd/start_consultation_ajax',
				type: 'POST',
				data: {
					iop_id: iop_id,
					patient_no: patient_no,
					<?php echo isset($csrf_token_name) ? $csrf_token_name : $this->security->get_csrf_token_name(); ?>: '<?php echo isset($csrf_hash) ? $csrf_hash : $this->security->get_csrf_hash(); ?>'
				},
				dataType: 'json',
				success: function(resp) {
					if (resp.ok) {
						$btn.html('<i class="fa fa-check"></i> Started').addClass('btn-success').removeClass('btn-warning');
						setTimeout(function(){ location.reload(); }, 600);
					} else if (resp.blocked) {
						var blockType = resp.block_type || '';
						if (blockType === 'DOCTOR_BUSY') {
							// Doctor busy — convert button to greyed-out "Doctor Busy"
							$btn.html('<i class="fa fa-clock-o"></i> Doctor Busy')
							    .removeClass('btn-warning start-consultation-btn')
							    .addClass('btn-default')
							    .prop('disabled', true)
							    .css({'cursor':'not-allowed','opacity':'0.6'})
							    .attr('title', resp.error);
						} else {
							// Payment gate block (default)
							var msg = resp.error || 'Payment required before consultation';
							$btn.html('<i class="fa fa-lock"></i> Payment Required')
							    .removeClass('btn-warning start-consultation-btn')
							    .addClass('btn-default')
							    .prop('disabled', true)
							    .css({'cursor':'not-allowed','opacity':'0.6'})
							    .attr('title', msg);
							// Inline cashier link if provided
							if (resp.payment_url) {
								var $cash = $('<a class="btn btn-xs btn-danger" style="margin-left:5px;" target="_blank">'
									+ '<i class="fa fa-credit-card"></i> Cashier</a>');
								$cash.attr('href', resp.payment_url);
								$btn.after($cash);
							}
						}
						// Show toast-style alert above the table
						var $alert = $('<div class="alert alert-danger alert-dismissable" style="margin-bottom:6px;">'
							+ '<i class="fa fa-ban"></i> <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>'
							+ '<strong>Blocked:</strong> ' + (resp.error || 'Action blocked') + '</div>');
						$btn.closest('td').prepend($alert);
					} else {
						alert('Error: ' + (resp.error || 'Unknown error'));
						$btn.prop('disabled', false).html('<i class="fa fa-play"></i> Start');
					}
				},
				error: function() {
					alert('Request failed. Please try again.');
					$btn.prop('disabled', false).html('<i class="fa fa-play"></i> Start');
				}
			});
		});

		// Clinical Clear Button - AJAX
		$(document).on('click', '.clinical-clear-btn', function() {
			var $btn = $(this);
			var iop_id = $btn.data('iop');
			var patient_no = $btn.data('patient');
			
			if (!confirm('Clinically clear this patient? This will lock the visit and disable further orders.')) {
				return;
			}
			
			$btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Processing...');
			
			$.ajax({
				url: '<?php echo base_url(); ?>app/opd/clinical_clear',
				type: 'POST',
				data: {
					iop_id: iop_id,
					patient_no: patient_no,
					<?php echo $this->security->get_csrf_token_name(); ?>: '<?php echo $this->security->get_csrf_hash(); ?>'
				},
				dataType: 'json',
				success: function(resp) {
					if (resp.status === 'success') {
						if (resp.promoted) {
							$('#promoted-patient-name').text(resp.promoted.patient_name + ' (' + resp.promoted.iop_id + ')');
							$('#live-queue-notification').fadeIn().delay(6000).fadeOut();
						}
						setTimeout(function(){ location.reload(); }, 800);
					} else {
						alert('Error: ' + (resp.message || 'Unknown error'));
						$btn.prop('disabled', false).html('<i class="fa fa-check-circle"></i> Clinically Clear');
					}
				},
				error: function() {
					alert('Request failed. Please try again.');
					$btn.prop('disabled', false).html('<i class="fa fa-check-circle"></i> Clinically Clear');
				}
			});
		});

		// Reopen Visit Button - Admin Only
		$(document).on('click', '.reopen-visit-btn', function() {
			var $btn = $(this);
			var iop_id = $btn.data('iop');
			
			if (!confirm('Reopen this visit? This will unlock the visit for further orders.')) {
				return;
			}
			
			$btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');
			
			$.ajax({
				url: '<?php echo base_url(); ?>app/opd/reopen_visit_ajax',
				type: 'POST',
				data: {
					iop_id: iop_id,
					<?php echo $this->security->get_csrf_token_name(); ?>: '<?php echo $this->security->get_csrf_hash(); ?>'
				},
				dataType: 'json',
				success: function(resp) {
					if (resp.status === 'success') {
						location.reload();
					} else {
						alert('Error: ' + (resp.message || 'Unknown error'));
						$btn.prop('disabled', false).html('<i class="fa fa-unlock"></i> Reopen');
					}
				},
				error: function() {
					alert('Request failed. Please try again.');
					$btn.prop('disabled', false).html('<i class="fa fa-unlock"></i> Reopen');
				}
			});
		});

		$(document).on('click', '.opd-status-btn', function() {
			var $btn = $(this);
			var iop_id = $btn.data('iop-id');
			var patient_no = $btn.data('patient-no');
			var status = $btn.data('status');
			var label = $.trim($btn.text());
			if (!confirm('Set patient status to: ' + label + '?')) { return; }
			var $cell = $btn.closest('td');
			$btn.prop('disabled', true).prepend('<i class="fa fa-spinner fa-spin"></i> ');
			$.ajax({
				url: '<?php echo base_url(); ?>app/opd/update_status_ajax',
				type: 'POST',
				data: { iop_id: iop_id, patient_no: patient_no, status: status, <?php echo $this->security->get_csrf_token_name(); ?>: '<?php echo $this->security->get_csrf_hash(); ?>' },
				dataType: 'json',
				success: function(resp) {
					if (resp.ok) {
						$cell.html(resp.widget);
					} else if (resp.status === 'blocked') {
						var messages = $.isArray(resp.messages) && resp.messages.length ? resp.messages : [resp.error || 'Status update was not allowed.'];
						$cell.find('.opd-status-block-alert').remove();
						$.each(messages, function(_, message) {
							var $alert = $('<div class="alert alert-danger alert-dismissable opd-status-block-alert" style="margin-bottom:6px;">'
								+ '<i class="fa fa-ban"></i> <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>'
								+ '<strong>Blocked:</strong> ' + $('<div>').text(message).html() + '</div>');
							$cell.prepend($alert);
						});
						$btn.prop('disabled', false);
						$btn.find('.fa-spinner').remove();
					} else {
						alert('Cannot update status:\n' + resp.error);
						$btn.prop('disabled', false);
						$btn.find('.fa-spinner').remove();
					}
				},
				error: function() {
					alert('Request failed. Please refresh and try again.');
					$btn.prop('disabled', false);
					$btn.find('.fa-spinner').remove();
				}
			});
		});
    </script>
    <!-- END BDAY -->





    <!-- Modal -->
    <div class="modal fade" id="myModal"  role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
        <form method="post" action="<?php echo base_url() ?>app/opd/reassign_doctor" onSubmit="return confirm('Are you sure you want to save?');">
            <?php if (isset($this->security)) { echo '<input type="hidden" name="'.$this->security->get_csrf_token_name().'" value="'.$this->security->get_csrf_hash().'">'; } ?>
            <input type="hidden" name="iop_id" id="reassign_iop_id" value="">
            <input type="hidden" name="patient_no" id="reassign_patient_no" value="">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                        <h4 class="modal-title" id="myModalLabel">Re-assign Doctor</h4>
                    </div>
                    <div class="modal-body">
                        <table class="table table-hover">
                            <tbody>
                                <tr>
                                    <td>Consultant Doctor</td>
                                    <td>
                                        <select name="doctor" id="reassign_doctor" class="form-control input-sm" style="width: 200px;" required>
                                            <option value="">- Consultant Doctor -</option>
                                            <?php
                                            foreach ($doctorList2 as $doctorList2) {
                                                if(isset($_POST['doctor']) && $_POST['doctor'] == $doctorList2->user_id) {
                                                    $selected = "selected='selected'";
                                                } else {
                                                    $selected = "";
                                                }
                                            ?>
                                                <option value="<?php echo $doctorList2->user_id; ?>" <?php echo $selected; ?>><?php echo $doctorList2->name; ?></option>
                                            <?php } ?>
                                        </select>
                                    </td>
                                </tr>

                            </tbody>
                        </table>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                        <button name="btnSubmit" class="btn btn-primary" id="btnSubmit" type="submit" style="font-size:12px;">Save</button>
                    </div>

                </div>
                <!-- /.modal-content -->
            </div>
            <!-- /.modal-dialog -->
        </form>
    </div>
    <!-- /.modal -->




</body>

</html>
