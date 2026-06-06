<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo isset($companyInfo->site_title) ? $companyInfo->site_title : 'HMS'; ?> — Nursing Cockpit</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
    <style>
        .nursing-card{border-left:4px solid #d2d6de;}
        .nursing-card.critical{border-left-color:#dd4b39;}
        .nursing-card.overdue_meds{border-left-color:#f39c12;}
        .nursing-card.overdue_vitals{border-left-color:#00c0ef;}
        .nursing-card.needs_review{border-left-color:#605ca8;}
        .nursing-card.high{border-left-color:#f39c12;}
        .nursing-card.medium,.nursing-card.low{border-left-color:#00c0ef;}
        .nursing-escalation-title{font-weight:bold;margin:10px 0;}
        .nursing-patient-title{font-weight:bold;font-size:15px;}
        .nursing-muted{color:#777;}
        .nursing-panel-list{max-height:360px;overflow:auto;}
        .nursing-refresh-bar{background:#fff;border:1px solid #d2d6de;padding:10px;margin-bottom:15px;}
        .nursing-drawer{position:fixed;right:-420px;top:0;width:420px;height:100%;background:#fff;z-index:1050;box-shadow:-3px 0 10px rgba(0,0,0,.25);overflow:auto;transition:right .2s ease;padding:15px;}
        .nursing-drawer.open{right:0;}
        .nursing-drawer-backdrop{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.25);z-index:1040;}
        .nursing-drawer-backdrop.open{display:block;}
    </style>
</head>
<body class="skin-blue">
    <?php require_once(APPPATH.'views/include/header.php');?>
    <div class="wrapper row-offcanvas row-offcanvas-left">
        <?php require_once(APPPATH.'views/include/sidebar.php');?>
        <aside class="right-side">
            <section class="content-header">
                <h1>Nursing Cockpit <small><?php echo date('l, M d, Y'); ?></small></h1>
                <ol class="breadcrumb">
                    <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                    <li class="active">Nursing Cockpit</li>
                </ol>
            </section>
            <section class="content">
                <?php if (isset($message) && $message != '') { echo $message; } ?>
                <?php $payload = isset($dashboard_payload) && is_array($dashboard_payload) ? $dashboard_payload : array(); ?>
                <?php $snapshot = isset($payload['snapshot']) ? $payload['snapshot'] : array(); ?>
                <?php $patients = isset($payload['patients']) ? $payload['patients'] : array(); ?>
                <?php $detainedOpd = isset($payload['detained_opd_patients']) ? $payload['detained_opd_patients'] : array(); ?>
                <?php $alerts = isset($payload['alerts']) ? $payload['alerts'] : array(); ?>
                <?php $pendingMeds = isset($payload['pending_medications']) ? $payload['pending_medications'] : array(); ?>
                <?php $overdueVitals = isset($payload['overdue_vitals']) ? $payload['overdue_vitals'] : array(); ?>
                <?php $meta = isset($payload['meta']) ? $payload['meta'] : array(); ?>
                <?php $refresh = isset($payload['refresh']) ? $payload['refresh'] : array('interval_seconds' => 90, 'stale_after_seconds' => 180, 'generated_at' => isset($payload['generated_at']) ? $payload['generated_at'] : date('Y-m-d H:i:s')); ?>
                <?php $shift = isset($payload['shift']) ? $payload['shift'] : array('label' => 'Current Shift'); ?>
                <?php $groups = isset($payload['escalation_groups']) ? $payload['escalation_groups'] : array(); ?>
                <?php $handover = isset($payload['handover_snapshot']) ? $payload['handover_snapshot'] : array(); ?>
                <?php if (isset($meta['partial']) && $meta['partial'] && isset($meta['warnings']) && count($meta['warnings']) > 0) { ?>
                    <div class="alert alert-warning"><i class="fa fa-warning"></i> Partial nursing data: <?php echo htmlspecialchars(implode(', ', $meta['warnings'])); ?></div>
                <?php } ?>

                <div class="nursing-refresh-bar">
                    <div class="row">
                        <div class="col-sm-8">
                            <strong><?php echo htmlspecialchars(isset($shift['label']) ? $shift['label'] : 'Current Shift'); ?></strong>
                            <span class="nursing-muted">Generated: <span id="nursingLastRefresh"><?php echo htmlspecialchars(isset($refresh['generated_at']) ? $refresh['generated_at'] : ''); ?></span></span>
                            <span id="nursingRefreshState" class="label label-success">FRESH</span>
                        </div>
                        <div class="col-sm-4 text-right">
                            <button type="button" class="btn btn-xs btn-default" id="nursingManualRefresh"><i class="fa fa-refresh"></i> Refresh</button>
                            <span class="nursing-muted">Auto: <?php echo isset($refresh['interval_seconds']) ? (int)$refresh['interval_seconds'] : 90; ?>s</span>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-3 col-xs-6">
                        <div class="small-box bg-aqua">
                            <div class="inner"><h3><?php echo isset($snapshot['active_patient_count']) ? (int)$snapshot['active_patient_count'] : 0; ?></h3><p>Active Ward Patients</p></div>
                            <div class="icon"><i class="fa fa-bed"></i></div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-xs-6">
                        <div class="small-box bg-teal">
                            <div class="inner"><h3><?php echo isset($snapshot['detained_opd_count']) ? (int)$snapshot['detained_opd_count'] : 0; ?></h3><p>Detained OPD / Observation</p></div>
                            <div class="icon"><i class="fa fa-stethoscope"></i></div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-xs-6">
                        <div class="small-box bg-red">
                            <div class="inner"><h3><?php echo isset($snapshot['critical_alert_count']) ? (int)$snapshot['critical_alert_count'] : 0; ?></h3><p>Critical Alerts</p></div>
                            <div class="icon"><i class="fa fa-warning"></i></div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-xs-6">
                        <div class="small-box bg-yellow">
                            <div class="inner"><h3><?php echo isset($snapshot['pending_medication_count']) ? (int)$snapshot['pending_medication_count'] : 0; ?></h3><p>Pending Medications</p></div>
                            <div class="icon"><i class="fa fa-medkit"></i></div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-3 col-xs-6">
                        <div class="small-box bg-purple">
                            <div class="inner"><h3><?php echo isset($snapshot['overdue_vitals_count']) ? (int)$snapshot['overdue_vitals_count'] : 0; ?></h3><p>Vitals Need Review</p></div>
                            <div class="icon"><i class="fa fa-heartbeat"></i></div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-8">
                        <div class="box box-primary">
                            <div class="box-header with-border">
                                <h3 class="box-title"><i class="fa fa-users"></i> Active Patients</h3>
                                <div class="box-tools pull-right"><a href="<?php echo base_url();?>app/nursing/dashboard" class="btn btn-box-tool"><i class="fa fa-refresh"></i></a></div>
                            </div>
                            <div class="box-body">
                                <?php if (count($patients) === 0) { ?>
                                    <div class="alert alert-info text-center">No active ward patients found for this shift.</div>
                                <?php } ?>
                                <?php $groupOrder = array('critical', 'high', 'watch', 'normal'); ?>
                                <?php foreach ($groupOrder as $groupKey) { ?>
                                    <?php $group = isset($groups[$groupKey]) ? $groups[$groupKey] : array('label' => strtoupper($groupKey), 'count' => 0, 'patients' => array()); ?>
                                    <?php if ((int)$group['count'] === 0) { continue; } ?>
                                    <div class="nursing-escalation-title"><?php echo htmlspecialchars($group['label']); ?> <span class="badge"><?php echo (int)$group['count']; ?></span></div>
                                    <?php foreach ($group['patients'] as $patient) { ?>
                                        <?php $priority = isset($patient['status_flags']['priority_state']) ? $patient['status_flags']['priority_state'] : 'normal'; ?>
                                        <div class="box box-solid nursing-card <?php echo htmlspecialchars($priority); ?>">
                                            <div class="box-body">
                                                <div class="row">
                                                    <div class="col-sm-7">
                                                        <div class="nursing-patient-title"><?php echo htmlspecialchars($patient['name']); ?></div>
                                                        <div class="nursing-muted">
                                                            <?php echo htmlspecialchars($patient['patient_no']); ?> · <?php echo htmlspecialchars($patient['encounter_id']); ?> · <?php echo htmlspecialchars(isset($patient['bed']['bed_name']) ? $patient['bed']['bed_name'] : 'No bed'); ?>
                                                        </div>
                                                        <div class="nursing-muted">
                                                            <?php echo htmlspecialchars(isset($patient['ward']['ward_name']) ? $patient['ward']['ward_name'] : 'Ward not set'); ?> · <?php echo htmlspecialchars(isset($patient['department_name']) ? $patient['department_name'] : ''); ?>
                                                        </div>
                                                    </div>
                                                    <div class="col-sm-3">
                                                        <span class="label label-default"><?php echo strtoupper(str_replace('_', ' ', htmlspecialchars($priority))); ?></span><br>
                                                        <small class="nursing-muted">Score: <?php echo isset($patient['priority_score']) ? (int)$patient['priority_score'] : 0; ?></small><br>
                                                        <small class="nursing-muted">Vitals: <?php echo htmlspecialchars(isset($patient['latest_vitals']['status']) ? $patient['latest_vitals']['status'] : 'unknown'); ?></small><br>
                                                        <small class="nursing-muted">Meds: <?php echo isset($patient['counts']['pending_medications']) ? (int)$patient['counts']['pending_medications'] : 0; ?></small>
                                                        <?php if (isset($patient['priority_reasons']) && count($patient['priority_reasons']) > 0) { ?>
                                                            <br><small class="nursing-muted"><?php echo htmlspecialchars(implode(', ', $patient['priority_reasons'])); ?></small>
                                                        <?php } ?>
                                                    </div>
                                                    <div class="col-sm-2 text-right">
                                                        <a href="<?php echo base_url();?>app/nursing/workspace/<?php echo urlencode($patient['encounter_id']); ?>" class="btn btn-xs btn-primary btn-block"><i class="fa fa-stethoscope"></i> Open Workspace</a>
                                                        <button type="button" class="btn btn-xs btn-default btn-block nursing-preview-btn" data-patient-id="<?php echo htmlspecialchars($patient['encounter_id']); ?>"><i class="fa fa-eye"></i> Quick Check</button>
                                                        <a href="<?php echo base_url();?>app/nursing/patient/<?php echo urlencode($patient['encounter_id']); ?>" class="btn btn-xs btn-default btn-block"><i class="fa fa-file-text-o"></i> Legacy Preview</a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php } ?>
                                <?php } ?>
                            </div>
                        </div>

                        <div class="box box-info">
                            <div class="box-header with-border">
                                <h3 class="box-title"><i class="fa fa-stethoscope"></i> Detained OPD / Observation (Read-only)</h3>
                            </div>
                            <div class="box-body">
                                <?php if (count($detainedOpd) === 0) { ?>
                                    <div class="alert alert-info text-center">No detained OPD patients found.</div>
                                <?php } ?>
                                <?php foreach ($detainedOpd as $row) { ?>
                                    <div class="box box-solid nursing-card needs_review">
                                        <div class="box-body">
                                            <div class="row">
                                                <div class="col-sm-8">
                                                    <div class="nursing-patient-title"><?php echo htmlspecialchars(isset($row['name']) ? $row['name'] : 'Patient'); ?></div>
                                                    <div class="nursing-muted"><?php echo htmlspecialchars(isset($row['patient_no']) ? $row['patient_no'] : ''); ?> · <?php echo htmlspecialchars(isset($row['encounter_id']) ? $row['encounter_id'] : ''); ?></div>
                                                    <div class="nursing-muted"><?php echo htmlspecialchars(isset($row['department_name']) ? $row['department_name'] : ''); ?> · Detained since <?php echo htmlspecialchars(isset($row['detained_since']) ? $row['detained_since'] : ''); ?></div>
                                                </div>
                                                <div class="col-sm-4 text-right">
                                                    <a href="<?php echo base_url();?>app/nursing/workspace/<?php echo urlencode(isset($row['encounter_id']) ? $row['encounter_id'] : ''); ?>" class="btn btn-xs btn-primary btn-block"><i class="fa fa-stethoscope"></i> Open Workspace</a>
                                                    <a href="<?php echo base_url();?>app/opd/view/<?php echo urlencode(isset($row['encounter_id']) ? $row['encounter_id'] : ''); ?>/<?php echo urlencode(isset($row['patient_no']) ? $row['patient_no'] : ''); ?>" class="btn btn-xs btn-default btn-block"><i class="fa fa-eye"></i> OPD View</a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="box box-danger">
                            <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-warning"></i> Critical Alerts</h3></div>
                            <div class="box-body nursing-panel-list">
                                <?php if (count($alerts) === 0) { ?><p class="text-muted">No critical alerts.</p><?php } ?>
                                <?php foreach ($alerts as $alert) { ?>
                                    <div class="callout callout-danger"><strong><?php echo htmlspecialchars($alert['patient_name']); ?></strong><br><?php echo htmlspecialchars($alert['label']); ?><br><small><?php echo htmlspecialchars($alert['created_at']); ?></small></div>
                                <?php } ?>
                            </div>
                        </div>
                        <div class="box box-warning">
                            <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-medkit"></i> Pending Medications</h3></div>
                            <div class="box-body nursing-panel-list">
                                <?php if (count($pendingMeds) === 0) { ?><p class="text-muted">No pending medication summary.</p><?php } ?>
                                <?php foreach ($pendingMeds as $med) { ?>
                                    <p><strong><?php echo htmlspecialchars($med['medication_name']); ?></strong><br><small><?php echo htmlspecialchars($med['patient_no']); ?> · <?php echo htmlspecialchars($med['status']); ?></small></p>
                                <?php } ?>
                            </div>
                        </div>
                        <div class="box box-info">
                            <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-heartbeat"></i> Vitals Need Review</h3></div>
                            <div class="box-body nursing-panel-list">
                                <?php if (count($overdueVitals) === 0) { ?><p class="text-muted">Vitals summary is current.</p><?php } ?>
                                <?php foreach ($overdueVitals as $vital) { ?>
                                    <p><strong><?php echo htmlspecialchars($vital['patient_name']); ?></strong><br><small><?php echo htmlspecialchars($vital['overdue_duration_label']); ?></small></p>
                                <?php } ?>
                            </div>
                        </div>
                        <div class="box box-success">
                            <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-exchange"></i> Handover Snapshot</h3></div>
                            <div class="box-body nursing-panel-list">
                                <p><strong>Patients needing review:</strong> <?php echo isset($handover['patients_needing_review']) ? count($handover['patients_needing_review']) : 0; ?></p>
                                <p><strong>Critical vitals:</strong> <?php echo isset($handover['critical_vitals']) ? count($handover['critical_vitals']) : 0; ?></p>
                                <p><strong>Unknown med state:</strong> <?php echo isset($handover['unknown_medications']) ? count($handover['unknown_medications']) : 0; ?></p>
                                <?php if (isset($handover['patients_needing_review']) && count($handover['patients_needing_review']) > 0) { ?>
                                    <hr>
                                    <?php foreach (array_slice($handover['patients_needing_review'], 0, 8) as $item) { ?>
                                        <p><strong><?php echo htmlspecialchars($item['name']); ?></strong><br><small><?php echo htmlspecialchars($item['escalation_band']); ?> · <?php echo htmlspecialchars($item['vitals_status']); ?> · Score <?php echo (int)$item['priority_score']; ?></small></p>
                                    <?php } ?>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </aside>
    </div>
    <div class="nursing-drawer-backdrop" id="nursingDrawerBackdrop"></div>
    <div class="nursing-drawer" id="nursingPreviewDrawer">
        <button type="button" class="close" id="nursingCloseDrawer">&times;</button>
        <h3 id="nursingDrawerTitle">Patient Preview</h3>
        <div id="nursingDrawerBody"><p class="text-muted">Select a patient to view recent activity.</p></div>
    </div>
    <script src="<?php echo base_url();?>public/js/jquery.min.js"></script>
    <script src="<?php echo base_url();?>public/js/bootstrap.min.js" type="text/javascript"></script>
    <script src="<?php echo base_url();?>public/js/AdminLTE/app.js" type="text/javascript"></script>
    <script type="text/javascript">
        var nursingRefreshInterval = <?php echo isset($refresh['interval_seconds']) ? (int)$refresh['interval_seconds'] : 90; ?> * 1000;
        var nursingStaleAfter = <?php echo isset($refresh['stale_after_seconds']) ? (int)$refresh['stale_after_seconds'] : 180; ?> * 1000;
        var nursingLastSuccess = new Date('<?php echo htmlspecialchars(isset($refresh['generated_at']) ? $refresh['generated_at'] : date('Y-m-d H:i:s')); ?>'.replace(' ', 'T')).getTime();
        function nursingSetRefreshState(state){
            var label = $('#nursingRefreshState');
            label.removeClass('label-success label-warning label-danger');
            if (state === 'FRESH') label.addClass('label-success');
            if (state === 'STALE') label.addClass('label-warning');
            if (state === 'UNREACHABLE') label.addClass('label-danger');
            label.text(state);
        }
        function nursingCheckStale(){
            if ((new Date().getTime() - nursingLastSuccess) > nursingStaleAfter) {
                nursingSetRefreshState('STALE');
            }
        }
        function nursingPollDashboard(){
            $.getJSON('<?php echo base_url();?>api/nursing/dashboard', function(payload){
                if (payload && payload.refresh && payload.refresh.generated_at) {
                    nursingLastSuccess = new Date(payload.refresh.generated_at.replace(' ', 'T')).getTime();
                    $('#nursingLastRefresh').text(payload.refresh.generated_at);
                    nursingSetRefreshState('FRESH');
                    if (!$('#nursingPreviewDrawer').hasClass('open')) {
                        window.location.reload();
                    }
                }
            }).fail(function(){
                nursingSetRefreshState('UNREACHABLE');
            });
        }
        function nursingEscape(value){
            return $('<div>').text(value === null || typeof value === 'undefined' ? '' : value).html();
        }
        function nursingOpenDrawer(){
            $('#nursingPreviewDrawer').addClass('open');
            $('#nursingDrawerBackdrop').addClass('open');
        }
        function nursingCloseDrawer(){
            $('#nursingPreviewDrawer').removeClass('open');
            $('#nursingDrawerBackdrop').removeClass('open');
        }
        $('.nursing-preview-btn').on('click', function(){
            var patientId = $(this).data('patient-id');
            $('#nursingDrawerTitle').text('Loading Patient Preview');
            $('#nursingDrawerBody').html('<p class="text-muted">Loading recent activity...</p>');
            nursingOpenDrawer();
            $.getJSON('<?php echo base_url();?>api/nursing/patient/' + encodeURIComponent(patientId) + '/summary', function(payload){
                var patient = payload.patient || {};
                var summary = payload.summary || {};
                var vitals = summary.latest_vitals || {};
                var meds = summary.pending_medications || [];
                var notes = summary.recent_notes || [];
                var reasons = summary.priority_reasons || [];
                $('#nursingDrawerTitle').text(patient.name || 'Patient Preview');
                var html = '';
                html += '<p><strong>Patient No:</strong> ' + nursingEscape(patient.patient_no) + '</p>';
                html += '<p><strong>Ward/Bed:</strong> ' + nursingEscape(patient.ward_name) + ' / ' + nursingEscape(patient.bed_name) + '</p>';
                html += '<p><strong>Priority:</strong> ' + nursingEscape(summary.priority_band || 'normal') + ' / ' + nursingEscape(summary.priority_score || 0) + '</p>';
                if (reasons.length > 0) html += '<p><strong>Reasons:</strong> ' + nursingEscape(reasons.join(', ')) + '</p>';
                html += '<hr><h4>Latest Vitals</h4>';
                html += '<p>Status: ' + nursingEscape(vitals.status || 'unknown') + '<br>Recorded: ' + nursingEscape(vitals.recorded_at || 'Not found') + '<br>BP: ' + nursingEscape(vitals.bp || '') + ' Pulse: ' + nursingEscape(vitals.pulse || '') + '<br>Temp: ' + nursingEscape(vitals.temperature || '') + ' Resp: ' + nursingEscape(vitals.respiratory_rate || '') + '</p>';
                html += '<hr><h4>Medication Summary</h4>';
                if (meds.length === 0) html += '<p class="text-muted">No visible medication items.</p>';
                for (var i = 0; i < meds.length && i < 5; i++) html += '<p><strong>' + nursingEscape(meds[i].medication_name) + '</strong><br><small>' + nursingEscape(meds[i].status) + '</small></p>';
                html += '<hr><h4>Recent Notes</h4>';
                if (notes.length === 0) html += '<p class="text-muted">No recent nurse notes found.</p>';
                for (var n = 0; n < notes.length && n < 3; n++) html += '<p><strong>' + nursingEscape(notes[n].recorded_at) + '</strong><br>' + nursingEscape(notes[n].note) + '</p>';
                html += '<hr><a class="btn btn-default btn-sm" href="<?php echo base_url();?>app/nursing/patient/' + encodeURIComponent(patient.encounter_id || patientId) + '">Open Full Read-Only Preview</a>';
                $('#nursingDrawerBody').html(html);
            }).fail(function(){
                $('#nursingDrawerBody').html('<div class="alert alert-warning">Unable to load preview. Existing dashboard data remains unchanged.</div>');
            });
        });
        $('#nursingCloseDrawer,#nursingDrawerBackdrop').on('click', nursingCloseDrawer);
        $('#nursingManualRefresh').on('click', function(){ window.location.reload(); });
        setInterval(nursingPollDashboard, nursingRefreshInterval);
        setInterval(nursingCheckStale, 15000);
    </script>
</body>
</html>
