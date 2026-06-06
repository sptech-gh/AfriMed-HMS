<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo isset($companyInfo->site_title) ? $companyInfo->site_title : 'HMS'; ?> — Bedside Workspace</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
    <style>
        .content{background:#f4f7fb;}
        .workspace-panel{min-height:260px;border-radius:8px;border-top-width:3px;box-shadow:0 8px 20px rgba(15,23,42,.06);}
        .workspace-panel .box-header{padding:14px 16px;}
        .workspace-panel .box-title{font-weight:600;color:#1f2937;}
        .workspace-panel .box-body{padding:16px;}
        .workspace-history{max-height:360px;overflow:auto;}
        .workspace-sticky-header{background:#fff;border-left:4px solid #3c8dbc;border-radius:8px;box-shadow:0 8px 24px rgba(15,23,42,.08);}
        .workspace-muted{color:#6b7280;}
        .workspace-action-box{border:1px solid #e5e7eb;border-radius:8px;padding:14px;margin-bottom:12px;background:#fff;box-shadow:0 1px 3px rgba(15,23,42,.04);}
        .workspace-timeline{max-height:520px;overflow:auto;}
        .workspace-timeline-item{border-left:3px solid #d2d6de;padding:0 0 16px 16px;margin-left:8px;margin-bottom:10px;}
        .workspace-timeline-item.vital{border-left-color:#00c0ef;}
        .workspace-timeline-item.note{border-left-color:#00a65a;}
        .workspace-timeline-item.procedure{border-left-color:#605ca8;}
        .workspace-timeline-item.medication{border-left-color:#f39c12;}
        .workspace-section-hidden{display:none;}
        .workspace-nav{margin-bottom:18px;background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:6px;box-shadow:0 1px 3px rgba(15,23,42,.04);}
        .workspace-nav .btn{border:0;border-radius:6px;margin-right:3px;}
        .workspace-nav-wrap{overflow-x:auto;white-space:nowrap;}
        .workspace-operational-strip{background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:14px 16px;margin-bottom:16px;box-shadow:0 1px 3px rgba(15,23,42,.04);}
        .workspace-patient-name{font-size:22px;font-weight:700;color:#111827;}
        .workspace-identity-item{padding:10px 12px;border-radius:8px;background:#f9fafc;border:1px solid #edf2f7;}
        .workspace-identity-label{display:block;color:#6b7280;font-size:11px;text-transform:uppercase;letter-spacing:.04em;margin-bottom:3px;}
        .workspace-identity-value{font-weight:600;color:#111827;}
        .workspace-kpi{padding:8px 0;}
        .workspace-kpi strong{display:block;color:#6b7280;font-size:11px;text-transform:uppercase;letter-spacing:.04em;}
        .workspace-kpi span{font-weight:600;color:#111827;}
        .workspace-focus-column{width:100%;}
        .workspace-focus-card{min-height:0;}
        .workspace-focus-card .workspace-history,.workspace-focus-card.workspace-timeline{max-height:none;overflow:visible;}
        .workspace-empty{border:1px dashed #d1d5db;border-radius:8px;background:#f9fafc;color:#6b7280;padding:14px;text-align:center;}
        .workspace-action-box h4{margin-top:0;font-weight:600;color:#111827;}
        .label{border-radius:999px;font-weight:600;}
        @media (min-width:1200px){.right-side .content{padding-right:28px;padding-left:28px;}}
    </style>
</head>
<body class="skin-blue">
    <?php require_once(APPPATH.'views/include/header.php');?>
    <div class="wrapper row-offcanvas row-offcanvas-left">
        <?php require_once(APPPATH.'views/include/sidebar.php');?>
        <aside class="right-side">
            <section class="content-header">
                <h1>Nursing Workspace <small>Patient Review & Shift Continuity</small></h1>
                <ol class="breadcrumb">
                    <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                    <li><a href="<?php echo base_url()?>app/nursing/dashboard">Nursing Cockpit</a></li>
                    <li class="active">Workspace</li>
                </ol>
            </section>
            <section class="content">
                <?php $summary = isset($workspace_payload) && is_array($workspace_payload) ? $workspace_payload : (isset($patient_summary) && is_array($patient_summary) ? $patient_summary : array()); ?>
                <?php $workspaceStatus = isset($summary['workspace_status']) ? (string)$summary['workspace_status'] : 'legacy_fallback'; ?>
                <?php $composedPanels = $workspaceStatus === 'stable_read_only' && isset($summary['composed_panels']) && is_array($summary['composed_panels']) ? $summary['composed_panels'] : array(); ?>
                <?php $headerPanel = isset($composedPanels['patient_header']) && is_array($composedPanels['patient_header']) ? $composedPanels['patient_header'] : array(); ?>
                <?php $vitalsPanel = isset($composedPanels['vitals']) && is_array($composedPanels['vitals']) ? $composedPanels['vitals'] : array(); ?>
                <?php $notesPanel = isset($composedPanels['notes']) && is_array($composedPanels['notes']) ? $composedPanels['notes'] : array(); ?>
                <?php $timelinePanel = isset($composedPanels['timeline']) && is_array($composedPanels['timeline']) ? $composedPanels['timeline'] : array(); ?>
                <?php $medicationPanel = isset($composedPanels['medication']) && is_array($composedPanels['medication']) ? $composedPanels['medication'] : array(); ?>
                <?php $ioPanel = isset($composedPanels['intake_output']) && is_array($composedPanels['intake_output']) ? $composedPanels['intake_output'] : array(); ?>
                <?php $handoverPanel = isset($composedPanels['shift_handover']) && is_array($composedPanels['shift_handover']) ? $composedPanels['shift_handover'] : array(); ?>
                <?php $patient = isset($summary['patient']) && is_array($summary['patient']) ? $summary['patient'] : array(); ?>
                <?php $pSummary = isset($summary['summary']) && is_array($summary['summary']) ? $summary['summary'] : array(); ?>
                <?php $headerItems = isset($headerPanel['items']) && is_array($headerPanel['items']) ? $headerPanel['items'] : array(); ?>
                <?php $vitalsItems = isset($vitalsPanel['items']) && is_array($vitalsPanel['items']) ? $vitalsPanel['items'] : array(); ?>
                <?php $vitalsHistory = isset($vitalsItems['history']) && is_array($vitalsItems['history']) ? $vitalsItems['history'] : (isset($summary['vitals_history']) && is_array($summary['vitals_history']) ? $summary['vitals_history'] : array()); ?>
                <?php $recentNotes = isset($notesPanel['items']) && is_array($notesPanel['items']) ? $notesPanel['items'] : (isset($summary['recent_notes']) && is_array($summary['recent_notes']) ? $summary['recent_notes'] : (isset($pSummary['recent_notes']) && is_array($pSummary['recent_notes']) ? $pSummary['recent_notes'] : array())); ?>
                <?php $recentProcedures = isset($summary['recent_procedures']) && is_array($summary['recent_procedures']) ? $summary['recent_procedures'] : (isset($pSummary['recent_procedures']) && is_array($pSummary['recent_procedures']) ? $pSummary['recent_procedures'] : array()); ?>
                <?php $pendingMedications = isset($summary['pending_medications']) && is_array($summary['pending_medications']) ? $summary['pending_medications'] : (isset($pSummary['pending_medications']) && is_array($pSummary['pending_medications']) ? $pSummary['pending_medications'] : array()); ?>
                <?php $medicationItems = isset($medicationPanel['items']) && is_array($medicationPanel['items']) ? $medicationPanel['items'] : array(); ?>
                <?php $recentMedicationAdministrations = isset($medicationItems['recent_administrations']) && is_array($medicationItems['recent_administrations']) ? $medicationItems['recent_administrations'] : (isset($summary['recent_medication_administrations']) && is_array($summary['recent_medication_administrations']) ? $summary['recent_medication_administrations'] : array()); ?>
                <?php $ioItems = isset($ioPanel['items']) && is_array($ioPanel['items']) ? $ioPanel['items'] : array(); ?>
                <?php $ioSummary = isset($ioItems['summary']) && is_array($ioItems['summary']) ? $ioItems['summary'] : (isset($summary['io_summary']) && is_array($summary['io_summary']) ? $summary['io_summary'] : array()); ?>
                <?php $ioRecentEntries = isset($ioItems['recent_entries']) && is_array($ioItems['recent_entries']) ? $ioItems['recent_entries'] : array(); ?>
                <?php $alerts = isset($summary['active_alerts']) && is_array($summary['active_alerts']) ? $summary['active_alerts'] : (isset($pSummary['active_alerts']) && is_array($pSummary['active_alerts']) ? $pSummary['active_alerts'] : array()); ?>
                <?php $timeline = isset($timelinePanel['items']) && is_array($timelinePanel['items']) ? $timelinePanel['items'] : (isset($summary['timeline']) && is_array($summary['timeline']) ? $summary['timeline'] : array()); ?>
                <?php $handoverItems = isset($handoverPanel['items']) && is_array($handoverPanel['items']) ? $handoverPanel['items'] : array(); ?>
                <?php $attentionIndicators = isset($handoverItems['attention_indicators']) && is_array($handoverItems['attention_indicators']) ? $handoverItems['attention_indicators'] : array(); ?>
                <?php $handoverRecentProcedures = isset($handoverItems['recent_procedures']) && is_array($handoverItems['recent_procedures']) ? $handoverItems['recent_procedures'] : array(); ?>
                <?php $handoverFollowUpProcedures = isset($handoverItems['follow_up_procedures']) && is_array($handoverItems['follow_up_procedures']) ? $handoverItems['follow_up_procedures'] : array(); ?>
                <?php $workspaceWriteMode = isset($nursing_workspace_write_mode) ? (bool)$nursing_workspace_write_mode : false; ?>
                <?php $workspaceMeta = isset($summary['meta']) && is_array($summary['meta']) ? $summary['meta'] : array(); ?>
                <?php $lastUpdatedAt = isset($workspaceMeta['last_updated_at']) ? (string)$workspaceMeta['last_updated_at'] : ''; ?>
                <div class="box box-primary workspace-sticky-header">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-stethoscope"></i> Nursing Workspace</h3>
                        <div class="pull-right">
                            <span class="label label-<?php echo $workspaceWriteMode ? 'warning' : 'success'; ?>"><?php echo $workspaceWriteMode ? 'WRITE MODE ENABLED' : 'READ-ONLY PILOT'; ?></span>
                            <span class="label label-primary">ACTIVE ENCOUNTER</span>
                        </div>
                    </div>
                    <div class="box-body">
                        <div class="workspace-patient-name"><?php echo htmlspecialchars(isset($headerPanel['title']) ? $headerPanel['title'] : (isset($patient['name']) ? $patient['name'] : 'Patient')); ?></div>
                        <div class="workspace-muted">Primary patient review, shift handover, and clinical awareness surface. Clinical writes remain in legacy workflows unless write mode is enabled.</div>
                        <hr>
                        <div class="row">
                            <div class="col-sm-3"><div class="workspace-identity-item"><span class="workspace-identity-label">Patient No</span><span class="workspace-identity-value"><?php echo htmlspecialchars(isset($headerItems['patient_no']) ? $headerItems['patient_no'] : (isset($patient['patient_no']) ? $patient['patient_no'] : '')); ?></span></div></div>
                            <div class="col-sm-3"><div class="workspace-identity-item"><span class="workspace-identity-label">Encounter</span><span class="workspace-identity-value"><?php echo htmlspecialchars(isset($headerItems['encounter_id']) ? $headerItems['encounter_id'] : (isset($patient['encounter_id']) ? $patient['encounter_id'] : '')); ?></span></div></div>
                            <div class="col-sm-3"><div class="workspace-identity-item"><span class="workspace-identity-label">Ward</span><span class="workspace-identity-value"><?php echo htmlspecialchars(isset($headerItems['ward_name']) ? $headerItems['ward_name'] : (isset($patient['ward_name']) ? $patient['ward_name'] : '')); ?></span></div></div>
                            <div class="col-sm-3"><div class="workspace-identity-item"><span class="workspace-identity-label">Bed</span><span class="workspace-identity-value"><?php echo htmlspecialchars(isset($headerItems['bed_name']) ? $headerItems['bed_name'] : (isset($patient['bed_name']) ? $patient['bed_name'] : '')); ?></span></div></div>
                        </div>
                    </div>
                    <div class="box-footer">
                        <a href="<?php echo base_url();?>app/nursing/dashboard" class="btn btn-default"><i class="fa fa-arrow-left"></i> Back to Dashboard</a>
                        <a href="<?php echo base_url();?>app/nursing/patient/<?php echo urlencode(isset($patient['encounter_id']) ? $patient['encounter_id'] : ''); ?>" class="btn btn-primary"><i class="fa fa-eye"></i> Read-only Preview</a>
                    </div>
                </div>
                <div class="workspace-operational-strip">
                    <div class="row">
                        <div class="col-sm-3 workspace-kpi"><strong>Workspace Mode</strong><span><?php echo $workspaceWriteMode ? 'Controlled Write Mode' : 'Read-Only Pilot'; ?></span></div>
                        <div class="col-sm-3 workspace-kpi"><strong>Legacy Writes</strong><span>Protected shortcuts available</span></div>
                        <div class="col-sm-3 workspace-kpi"><strong>Last Updated</strong><span><?php echo htmlspecialchars($lastUpdatedAt !== '' ? $lastUpdatedAt : 'Not supplied'); ?></span></div>
                        <div class="col-sm-3 workspace-kpi"><strong>Status</strong><span><?php echo htmlspecialchars($workspaceStatus); ?></span></div>
                    </div>
                </div>
                <div class="workspace-nav-wrap">
                <div class="btn-group workspace-nav" role="group">
                    <button type="button" class="btn btn-default workspace-section-btn" data-section="overview"><i class="fa fa-th-large"></i> Overview</button>
                    <button type="button" class="btn btn-default workspace-section-btn" data-section="vitals"><i class="fa fa-heartbeat"></i> Vitals</button>
                    <button type="button" class="btn btn-default workspace-section-btn" data-section="notes"><i class="fa fa-file-text"></i> Notes</button>
                    <button type="button" class="btn btn-default workspace-section-btn" data-section="procedures"><i class="fa fa-medkit"></i> Procedures</button>
                    <button type="button" class="btn btn-default workspace-section-btn" data-section="medication"><i class="fa fa-bell"></i> Medication</button>
                    <button type="button" class="btn btn-default workspace-section-btn" data-section="io"><i class="fa fa-tint"></i> Intake / Output</button>
                    <button type="button" class="btn btn-default workspace-section-btn" data-section="handover"><i class="fa fa-exchange"></i> Handover</button>
                    <button type="button" class="btn btn-default workspace-section-btn" data-section="clinical_context"><i class="fa fa-stethoscope"></i> Clinical Context</button>
                    <button type="button" class="btn btn-default workspace-section-btn" data-section="timeline"><i class="fa fa-clock-o"></i> Timeline</button>
                </div>
                </div>
                <div class="box box-default" id="workspaceActionShortcutsPanel" data-workspace-section="overview">
                    <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-bolt"></i> Legacy Write Workflow Shortcuts</h3></div>
                    <div class="box-body">
                        <p class="workspace-muted">Use these protected legacy workflows only when a clinical write action is required.</p>
                        <a class="btn btn-sm btn-success" href="<?php echo base_url();?>app/nurse_module/record_vitals/<?php echo urlencode(isset($patient['encounter_id']) ? $patient['encounter_id'] : ''); ?>/<?php echo urlencode(isset($patient['patient_no']) ? $patient['patient_no'] : ''); ?>"><i class="fa fa-heartbeat"></i> Record Vitals</a>
                        <a class="btn btn-sm btn-info" href="<?php echo base_url();?>app/nurse_module/medication/<?php echo urlencode(isset($patient['encounter_id']) ? $patient['encounter_id'] : ''); ?>/<?php echo urlencode(isset($patient['patient_no']) ? $patient['patient_no'] : ''); ?>"><i class="fa fa-medkit"></i> Administer Medication</a>
                        <a class="btn btn-sm btn-primary" href="<?php echo base_url();?>app/nurse_module/intake_output/<?php echo urlencode(isset($patient['encounter_id']) ? $patient['encounter_id'] : ''); ?>/<?php echo urlencode(isset($patient['patient_no']) ? $patient['patient_no'] : ''); ?>"><i class="fa fa-tint"></i> Record IO</a>
                        <a class="btn btn-sm btn-warning" href="<?php echo base_url();?>app/nurse_module/bed_side_procedure/<?php echo urlencode(isset($patient['encounter_id']) ? $patient['encounter_id'] : ''); ?>/<?php echo urlencode(isset($patient['patient_no']) ? $patient['patient_no'] : ''); ?>"><i class="fa fa-plus-square"></i> Add Procedure</a>
                        <a class="btn btn-sm btn-default" href="<?php echo base_url();?>app/nurse_module/messages/<?php echo urlencode(isset($patient['encounter_id']) ? $patient['encounter_id'] : ''); ?>/<?php echo urlencode(isset($patient['patient_no']) ? $patient['patient_no'] : ''); ?>"><i class="fa fa-comments"></i> Request Review</a>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4">
                        <div class="box box-info workspace-panel" id="workspaceVitalsPanel" data-workspace-section="overview vitals">
                            <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-heartbeat"></i> Vitals</h3></div>
                            <div class="box-body">
                                <?php $latest = isset($vitalsItems['latest']) && is_array($vitalsItems['latest']) ? $vitalsItems['latest'] : (isset($pSummary['latest_vitals']) && is_array($pSummary['latest_vitals']) ? $pSummary['latest_vitals'] : array()); ?>
                                <div class="workspace-action-box">
                                    <h4>Latest Vitals</h4>
                                    <strong>Status:</strong> <?php echo htmlspecialchars(isset($latest['status']) ? $latest['status'] : 'unknown'); ?><br>
                                    <strong>Recorded:</strong> <span id="nursingVitalsRecordedAt"><?php echo htmlspecialchars(isset($latest['recorded_at']) ? $latest['recorded_at'] : 'Not found'); ?></span><br>
                                    <strong>BP:</strong> <span id="nursingVitalsBp"><?php echo htmlspecialchars(isset($latest['bp']) ? $latest['bp'] : ''); ?></span><br>
                                    <strong>Pulse:</strong> <span id="nursingVitalsPulse"><?php echo htmlspecialchars(isset($latest['pulse']) ? $latest['pulse'] : ''); ?></span><br>
                                    <strong>Temp:</strong> <span id="nursingVitalsTemp"><?php echo htmlspecialchars(isset($latest['temperature']) ? $latest['temperature'] : ''); ?></span><br>
                                    <strong>Resp:</strong> <span id="nursingVitalsResp"><?php echo htmlspecialchars(isset($latest['respiratory_rate']) ? $latest['respiratory_rate'] : ''); ?></span>
                                </div>
                                <?php if ($workspaceWriteMode) { ?>
                                <h4>Record Vitals</h4>
                                <div id="nursingVitalsFeedback"></div>
                                <form id="nursingVitalsForm" autocomplete="off">
                                    <input type="hidden" name="patient_no" value="<?php echo htmlspecialchars(isset($patient['patient_no']) ? $patient['patient_no'] : ''); ?>">
                                    <input type="hidden" name="idempotency_key" id="nursingIdempotencyKey" value="">
                                    <?php if (isset($this->security) && method_exists($this->security, 'get_csrf_token_name')) { ?>
                                        <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                                    <?php } ?>
                                    <div class="row">
                                        <div class="col-xs-6">
                                            <label>BP</label>
                                            <input class="form-control input-lg" name="bp" placeholder="120/80" inputmode="numeric">
                                        </div>
                                        <div class="col-xs-6">
                                            <label>Temperature</label>
                                            <input class="form-control input-lg" name="temperature" placeholder="37.2" inputmode="decimal">
                                        </div>
                                    </div>
                                    <div class="row" style="margin-top:10px;">
                                        <div class="col-xs-6">
                                            <label>Pulse</label>
                                            <input class="form-control input-lg" name="pulse_rate" placeholder="98" inputmode="numeric">
                                        </div>
                                        <div class="col-xs-6">
                                            <label>Respiratory Rate</label>
                                            <input class="form-control input-lg" name="respiration" placeholder="18" inputmode="numeric">
                                        </div>
                                    </div>
                                    <div class="row" style="margin-top:10px;">
                                        <div class="col-xs-6">
                                            <label>Weight</label>
                                            <input class="form-control input-lg" name="weight" placeholder="70" inputmode="decimal">
                                        </div>
                                        <div class="col-xs-6">
                                            <label>SpO2 (optional)</label>
                                            <input class="form-control input-lg" name="spo2" placeholder="98" inputmode="numeric">
                                        </div>
                                    </div>
                                    <div class="row" style="margin-top:12px;">
                                        <div class="col-xs-12">
                                            <button type="submit" class="btn btn-success btn-lg btn-block" id="nursingVitalsSaveBtn"><i class="fa fa-save"></i> Save Vitals</button>
                                        </div>
                                    </div>
                                </form>
                                <?php } else { ?>
                                <a class="btn btn-success btn-block" href="<?php echo base_url();?>app/nurse_module/record_vitals/<?php echo urlencode(isset($patient['encounter_id']) ? $patient['encounter_id'] : ''); ?>/<?php echo urlencode(isset($patient['patient_no']) ? $patient['patient_no'] : ''); ?>"><i class="fa fa-heartbeat"></i> Record Vitals in Legacy Workflow</a>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="box box-success workspace-panel" id="workspaceNotesPanel" data-workspace-section="overview notes">
                            <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-file-text"></i> Quick Note</h3></div>
                            <div class="box-body">
                                <?php if ($workspaceWriteMode) { ?>
                                <div id="nursingNoteFeedback"></div>
                                <form id="nursingNoteForm" autocomplete="off">
                                    <input type="hidden" name="patient_no" value="<?php echo htmlspecialchars(isset($patient['patient_no']) ? $patient['patient_no'] : ''); ?>">
                                    <input type="hidden" name="encounter_id" value="<?php echo htmlspecialchars(isset($patient['encounter_id']) ? $patient['encounter_id'] : ''); ?>">
                                    <input type="hidden" name="idempotency_key" id="nursingNoteIdempotencyKey" value="">
                                    <?php if (isset($this->security) && method_exists($this->security, 'get_csrf_token_name')) { ?>
                                        <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                                    <?php } ?>
                                    <div class="form-group">
                                        <label>Focus</label>
                                        <input class="form-control" name="focus" placeholder="Observation, complaint, wound care, handover">
                                    </div>
                                    <div class="form-group">
                                        <label>Note</label>
                                        <textarea class="form-control" name="note_text" rows="5" placeholder="Enter concise nursing note"></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-success btn-block" id="nursingNoteSaveBtn"><i class="fa fa-save"></i> Save Note</button>
                                </form>
                                <?php } else { ?>
                                <div class="workspace-action-box">
                                    <strong>Read-only pilot mode</strong><br>
                                    <span class="workspace-muted">Use the legacy nursing workflow for nursing notes and clinical writes.</span>
                                </div>
                                <a class="btn btn-default btn-block" href="<?php echo base_url();?>app/nurse_module/messages/<?php echo urlencode(isset($patient['encounter_id']) ? $patient['encounter_id'] : ''); ?>/<?php echo urlencode(isset($patient['patient_no']) ? $patient['patient_no'] : ''); ?>"><i class="fa fa-comments"></i> Request Review in Legacy Workflow</a>
                                <?php } ?>
                            </div>
                        </div>
                        <div class="box box-warning workspace-panel" id="workspaceAwarenessPanel" data-workspace-section="overview medication">
                            <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-bell"></i> Operational Awareness</h3></div>
                            <div class="box-body workspace-history">
                                <h4>Alerts</h4>
                                <?php if (count($alerts) === 0) { ?><div class="workspace-empty"><i class="fa fa-check-circle"></i><br>No active alerts.</div><?php } ?>
                                <?php foreach ($alerts as $alert) { ?>
                                    <p><span class="label label-<?php echo isset($alert['severity']) && $alert['severity'] === 'critical' ? 'danger' : 'warning'; ?>"><?php echo htmlspecialchars(isset($alert['severity']) ? $alert['severity'] : 'info'); ?></span> <?php echo htmlspecialchars(isset($alert['label']) ? $alert['label'] : 'Alert'); ?></p>
                                <?php } ?>
                                <hr>
                                <h4>Medication Due</h4>
                                <?php if (count($pendingMedications) === 0) { ?><div class="workspace-empty"><i class="fa fa-check-circle"></i><br>No due medications found.</div><?php } ?>
                                <?php foreach ($pendingMedications as $medication) { ?>
                                    <p><strong><?php echo htmlspecialchars(isset($medication['medication_name']) ? $medication['medication_name'] : 'Medication'); ?></strong><br><span class="workspace-muted"><?php echo htmlspecialchars(isset($medication['dose']) ? $medication['dose'] : ''); ?></span></p>
                                <?php } ?>
                                <hr>
                                <h4>Recently Administered</h4>
                                <?php if (count($recentMedicationAdministrations) === 0) { ?><div class="workspace-empty"><i class="fa fa-info-circle"></i><br>No recent medication administration found.</div><?php } ?>
                                <?php foreach ($recentMedicationAdministrations as $admin) { ?>
                                    <p><strong><?php echo htmlspecialchars(isset($admin['medication_name']) ? $admin['medication_name'] : 'Medication'); ?></strong><br><span class="workspace-muted"><?php echo htmlspecialchars(isset($admin['status']) ? strtoupper($admin['status']) : 'RECORDED'); ?> · <?php echo htmlspecialchars(isset($admin['recorded_at']) ? $admin['recorded_at'] : ''); ?><?php if (isset($admin['actor']) && trim((string)$admin['actor']) !== '') { ?> · By <?php echo htmlspecialchars($admin['actor']); ?><?php } ?></span></p>
                                <?php } ?>
                            </div>
                        </div>
						<div class="box box-primary workspace-panel" id="workspaceClinicalContextPanel" data-workspace-section="overview clinical_context">
							<div class="box-header with-border"><h3 class="box-title"><i class="fa fa-stethoscope"></i> Clinical Context (Read-only)</h3></div>
							<div class="box-body workspace-history">
								<?php $clinicalContext = isset($workspace_payload['clinical_context']) && is_array($workspace_payload['clinical_context']) ? $workspace_payload['clinical_context'] : array(); ?>
								<?php $hx = isset($clinicalContext['history']) && is_array($clinicalContext['history']) ? $clinicalContext['history'] : array(); ?>
								<?php $dt = isset($clinicalContext['detention']) && is_array($clinicalContext['detention']) ? $clinicalContext['detention'] : array(); ?>
								<?php $complaints = isset($clinicalContext['complaints']) && is_array($clinicalContext['complaints']) ? $clinicalContext['complaints'] : array(); ?>
								<?php $diagnoses = isset($clinicalContext['diagnoses']) && is_array($clinicalContext['diagnoses']) ? $clinicalContext['diagnoses'] : array(); ?>
								<?php $prescriptions = isset($clinicalContext['prescriptions']) && is_array($clinicalContext['prescriptions']) ? $clinicalContext['prescriptions'] : array(); ?>
								<?php $labs = isset($clinicalContext['labs']) && is_array($clinicalContext['labs']) ? $clinicalContext['labs'] : array(); ?>

								<?php if (!empty($hx['allergies']) || !empty($hx['warnings'])) { ?>
									<div style="margin-bottom:10px;">
										<?php if (!empty($hx['allergies'])) { ?><p><span class="label label-danger">ALLERGIES</span> <?php echo htmlspecialchars((string)$hx['allergies']); ?></p><?php } ?>
										<?php if (!empty($hx['warnings'])) { ?><p><span class="label label-warning">WARNINGS</span> <?php echo htmlspecialchars((string)$hx['warnings']); ?></p><?php } ?>
									</div>
								<?php } ?>

								<?php if (!empty($dt) && (!empty($dt['is_detained']) || !empty($dt['converted_to_admission']))) { ?>
									<div style="margin-bottom:10px;">
										<?php if (!empty($dt['is_detained'])) { ?><p><span class="label label-danger">DETAINED</span> <?php echo htmlspecialchars((string)(isset($dt['detention_start_at']) ? $dt['detention_start_at'] : '')); ?></p><?php } ?>
										<?php if (!empty($dt['converted_to_admission'])) { ?><p><span class="label label-info">ADMITTED</span> <?php echo htmlspecialchars((string)(isset($dt['converted_ipd_iop_id']) ? $dt['converted_ipd_iop_id'] : '')); ?></p><?php } ?>
									</div>
								<?php } ?>

								<h4>Complaints</h4>
								<?php if (count($complaints) === 0) { ?><div class="workspace-empty"><i class="fa fa-info-circle"></i><br>No complaints recorded.</div><?php } ?>
								<?php foreach (array_slice($complaints, 0, 5) as $row) { ?>
									<p><strong><?php echo htmlspecialchars(isset($row['complaint']) ? (string)$row['complaint'] : 'Complaint'); ?></strong><?php if (!empty($row['complaint_text'])) { ?><br><span class="workspace-muted"><?php echo htmlspecialchars((string)$row['complaint_text']); ?></span><?php } ?></p>
								<?php } ?>

								<hr>
								<h4>Diagnoses</h4>
								<?php if (count($diagnoses) === 0) { ?><div class="workspace-empty"><i class="fa fa-info-circle"></i><br>No diagnoses recorded.</div><?php } ?>
								<?php foreach (array_slice($diagnoses, 0, 5) as $row) { ?>
									<p><strong><?php echo htmlspecialchars(isset($row['diagnosis']) ? (string)$row['diagnosis'] : 'Diagnosis'); ?></strong><?php if (!empty($row['icd_code'])) { ?> <span class="label label-default">ICD: <?php echo htmlspecialchars((string)$row['icd_code']); ?></span><?php } ?></p>
								<?php } ?>

								<hr>
								<h4>Prescriptions</h4>
								<?php if (count($prescriptions) === 0) { ?><div class="workspace-empty"><i class="fa fa-info-circle"></i><br>No prescriptions found.</div><?php } ?>
								<?php foreach (array_slice($prescriptions, 0, 5) as $row) { ?>
									<p><strong><?php echo htmlspecialchars(isset($row['medication']) ? (string)$row['medication'] : 'Medication'); ?></strong><?php if (!empty($row['dosage'])) { ?> <span class="workspace-muted">· <?php echo htmlspecialchars((string)$row['dosage']); ?></span><?php } ?><?php if (!empty($row['dispensing_status'])) { ?> <span class="label label-info"><?php echo htmlspecialchars((string)$row['dispensing_status']); ?></span><?php } ?></p>
								<?php } ?>

								<hr>
								<h4>Laboratory</h4>
								<?php if (count($labs) === 0) { ?><div class="workspace-empty"><i class="fa fa-info-circle"></i><br>No lab requests found.</div><?php } ?>
								<?php foreach (array_slice($labs, 0, 5) as $row) { ?>
									<p><strong><?php echo htmlspecialchars(isset($row['test_name']) ? (string)$row['test_name'] : 'Lab test'); ?></strong> <span class="label label-<?php echo (isset($row['status']) && $row['status'] === 'completed') ? 'success' : 'warning'; ?>"><?php echo htmlspecialchars(isset($row['status']) ? (string)$row['status'] : 'pending'); ?></span><?php if (!empty($row['result'])) { ?><br><span class="workspace-muted"><?php echo htmlspecialchars((string)$row['result']); ?></span><?php } ?></p>
								<?php } ?>
							</div>
						</div>
                        <div class="box box-info workspace-panel" id="workspaceIntakeOutputPanel" data-workspace-section="overview medication io">
                            <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-tint"></i> Intake / Output</h3></div>
                            <div class="box-body workspace-history">
                                <div class="workspace-action-box">
                                    <strong>Intake:</strong> <?php echo htmlspecialchars(isset($ioSummary['intake_total']) ? $ioSummary['intake_total'] : 0); ?><br>
                                    <strong>Output:</strong> <?php echo htmlspecialchars(isset($ioSummary['output_total']) ? $ioSummary['output_total'] : 0); ?><br>
                                    <strong>Balance:</strong> <?php echo htmlspecialchars(isset($ioSummary['balance']) ? $ioSummary['balance'] : 0); ?>
                                </div>
                                <?php if (count($ioRecentEntries) === 0) { ?><div class="workspace-empty"><i class="fa fa-info-circle"></i><br><?php echo htmlspecialchars(isset($ioPanel['empty_state']) ? $ioPanel['empty_state'] : 'No intake/output records found.'); ?></div><?php } ?>
                                <?php foreach ($ioRecentEntries as $entry) { ?>
                                    <p><span class="label label-<?php echo isset($entry['io_type']) && $entry['io_type'] === 'output' ? 'warning' : 'info'; ?>"><?php echo htmlspecialchars(isset($entry['io_type']) ? strtoupper($entry['io_type']) : 'IO'); ?></span> <?php echo htmlspecialchars(isset($entry['summary']) ? $entry['summary'] : 'Record'); ?><br><span class="workspace-muted"><?php echo htmlspecialchars(isset($entry['recorded_at']) ? $entry['recorded_at'] : ''); ?></span></p>
                                <?php } ?>
                            </div>
                        </div>
                        <div class="box box-danger workspace-panel" id="workspaceHandoverPanel" data-workspace-section="overview handover">
                            <div class="box-header with-border">
                                <h3 class="box-title">
                                    <i class="fa fa-exchange"></i>
                                    <?php echo htmlspecialchars(isset($handoverPanel['title']) ? $handoverPanel['title'] : 'Shift Handover Summary'); ?>
                                </h3>
                            </div>
                            <div class="box-body workspace-history">
                                <h4>Attention Indicators</h4>

                                <?php if (count($attentionIndicators) === 0) { ?>
                                    <div class="workspace-empty"><i class="fa fa-check-circle"></i><br>
                                        <?php echo htmlspecialchars(isset($handoverPanel['empty_state']) ? $handoverPanel['empty_state'] : 'No handover concerns found.'); ?>
                                    </div>
                                <?php } ?>

                                <?php foreach ($attentionIndicators as $indicator) { ?>
                                    <p>
                                        <span class="label label-<?php echo isset($indicator['severity']) && $indicator['severity'] === 'critical' ? 'danger' : 'warning'; ?>">
                                            <?php echo htmlspecialchars(isset($indicator['severity']) ? strtoupper($indicator['severity']) : 'INFO'); ?>
                                        </span>
                                        <?php echo htmlspecialchars(isset($indicator['label']) ? $indicator['label'] : 'Attention required'); ?>
                                    </p>
                                <?php } ?>

        <hr>

        <p>
            <strong>Pending meds:</strong>
            <?php echo htmlspecialchars(isset($handoverPanel['meta']['pending_medication_count']) ? $handoverPanel['meta']['pending_medication_count'] : 0); ?>
        </p>

        <p>
            <strong>Recent notes:</strong>
            <?php echo htmlspecialchars(isset($handoverPanel['meta']['recent_note_count']) ? $handoverPanel['meta']['recent_note_count'] : 0); ?>
        </p>

        <p>
            <strong>IO balance:</strong>
            <?php echo htmlspecialchars(isset($handoverItems['io_summary']['balance']) ? $handoverItems['io_summary']['balance'] : 0); ?>
        </p>

        <hr>

        <h4>Recent Procedures</h4>
        <?php if (count($handoverRecentProcedures) === 0) { ?><div class="workspace-empty"><i class="fa fa-info-circle"></i><br>No recent bedside procedures.</div><?php } ?>
        <?php foreach ($handoverRecentProcedures as $procedure) { ?>
            <p>
                <strong><?php echo htmlspecialchars(isset($procedure['procedure_name']) ? $procedure['procedure_name'] : 'Bedside procedure'); ?></strong>
                <span class="label label-<?php echo isset($procedure['severity_level']) && $procedure['severity_level'] === 'EMERGENCY' ? 'danger' : (isset($procedure['severity_level']) && $procedure['severity_level'] === 'HIGH' ? 'warning' : (isset($procedure['severity_level']) && $procedure['severity_level'] === 'MEDIUM' ? 'info' : 'default')); ?>"><?php echo htmlspecialchars(isset($procedure['severity_level']) ? $procedure['severity_level'] : 'ROUTINE'); ?></span>
                <span class="label label-success"><?php echo htmlspecialchars(isset($procedure['outcome_status']) ? $procedure['outcome_status'] : 'SUCCESS'); ?></span>
                <span class="label label-<?php echo isset($procedure['follow_up_required']) && $procedure['follow_up_required'] ? 'warning' : 'default'; ?>"><?php echo isset($procedure['follow_up_required']) && $procedure['follow_up_required'] ? 'Follow-up' : 'No follow-up'; ?></span>
            </p>
        <?php } ?>

        <h4>Follow-up Required</h4>
        <?php if (count($handoverFollowUpProcedures) === 0) { ?><div class="workspace-empty"><i class="fa fa-check-circle"></i><br>No procedure follow-up flags.</div><?php } ?>
        <?php foreach ($handoverFollowUpProcedures as $procedure) { ?>
            <p><?php echo htmlspecialchars(isset($procedure['procedure_name']) ? $procedure['procedure_name'] : 'Bedside procedure'); ?> · <?php echo htmlspecialchars(isset($procedure['clinical_indication']) ? $procedure['clinical_indication'] : 'Unknown indication'); ?></p>
        <?php } ?>
    </div>
</div>
                    </div>
                    <div class="col-md-4">
                        <div class="box box-primary workspace-panel" id="workspaceVitalsHistoryPanel" data-workspace-section="overview vitals">
                            <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-history"></i> Recent Vitals</h3></div>
                            <div class="box-body workspace-history" id="nursingVitalsHistory">
                                <?php if (count($vitalsHistory) === 0) { ?><div class="workspace-empty"><i class="fa fa-info-circle"></i><br>No vitals history found.</div><?php } ?>
                                <?php foreach ($vitalsHistory as $vital) { ?>
                                    <div class="workspace-action-box">
                                        <strong><?php echo htmlspecialchars(isset($vital['recorded_at']) ? $vital['recorded_at'] : ''); ?></strong><br>
                                        BP: <?php echo htmlspecialchars(isset($vital['bp']) ? $vital['bp'] : ''); ?> ·
                                        Pulse: <?php echo htmlspecialchars(isset($vital['pulse']) ? $vital['pulse'] : ''); ?> ·
                                        Temp: <?php echo htmlspecialchars(isset($vital['temperature']) ? $vital['temperature'] : ''); ?><br>
                                        Resp: <?php echo htmlspecialchars(isset($vital['respiratory_rate']) ? $vital['respiratory_rate'] : ''); ?> ·
                                        SpO2: <?php echo htmlspecialchars(isset($vital['spo2']) ? $vital['spo2'] : ''); ?>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                        <div class="box box-success workspace-panel" id="workspaceRecentNotesPanel" data-workspace-section="overview notes">
                            <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-list"></i> Recent Notes</h3></div>
                            <div class="box-body workspace-history" id="nursingRecentNotes">
                                <?php if (count($recentNotes) === 0) { ?><div class="workspace-empty"><i class="fa fa-info-circle"></i><br><?php echo htmlspecialchars(isset($notesPanel['empty_state']) ? $notesPanel['empty_state'] : 'No recent nurse notes.'); ?></div><?php } ?>
                                <?php foreach ($recentNotes as $note) { ?>
                                    <div class="workspace-action-box">
                                        <strong><?php echo htmlspecialchars(isset($note['recorded_at']) ? $note['recorded_at'] : ''); ?></strong>
                                        <?php if (isset($note['focus']) && trim((string)$note['focus']) !== '') { ?><span class="label label-default"><?php echo htmlspecialchars($note['focus']); ?></span><?php } ?><br>
                                        <?php echo htmlspecialchars(isset($note['note']) ? $note['note'] : ''); ?>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                        <div class="box box-default workspace-panel" id="workspaceProcedurePanel" data-workspace-section="overview procedures">
                            <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-medkit"></i> Recent Procedures</h3></div>
                            <div class="box-body workspace-history">
                                <?php if (count($recentProcedures) === 0) { ?><div class="workspace-empty"><i class="fa fa-info-circle"></i><br>No recent bedside procedures.</div><?php } ?>
                                <?php foreach ($recentProcedures as $procedure) { ?>
                                    <p>
                                        <strong><?php echo htmlspecialchars(isset($procedure['procedure_name']) ? $procedure['procedure_name'] : 'Bedside procedure'); ?></strong>
                                        <span class="label label-<?php echo isset($procedure['severity_level']) && $procedure['severity_level'] === 'EMERGENCY' ? 'danger' : (isset($procedure['severity_level']) && $procedure['severity_level'] === 'HIGH' ? 'warning' : (isset($procedure['severity_level']) && $procedure['severity_level'] === 'MEDIUM' ? 'info' : 'default')); ?>"><?php echo htmlspecialchars(isset($procedure['severity_level']) ? $procedure['severity_level'] : 'ROUTINE'); ?></span>
                                        <span class="label label-success"><?php echo htmlspecialchars(isset($procedure['outcome_status']) ? $procedure['outcome_status'] : 'SUCCESS'); ?></span>
                                        <span class="label label-<?php echo isset($procedure['follow_up_required']) && $procedure['follow_up_required'] ? 'warning' : 'default'; ?>"><?php echo isset($procedure['follow_up_required']) && $procedure['follow_up_required'] ? 'Follow-up' : 'No follow-up'; ?></span>
                                        <br>
                                        <span class="workspace-muted"><?php echo htmlspecialchars(isset($procedure['recorded_at']) ? $procedure['recorded_at'] : ''); ?> · <?php echo htmlspecialchars(isset($procedure['clinical_indication']) ? $procedure['clinical_indication'] : 'Unknown indication'); ?></span>
                                    </p>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="box box-solid" id="workspaceTimelinePanel" data-workspace-section="overview timeline">
                    <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-clock-o"></i> Recent Clinical Timeline</h3></div>
                    <div class="box-body workspace-timeline" id="nursingPatientTimeline">
                        <?php if (count($timeline) === 0) { ?><div class="workspace-empty"><i class="fa fa-info-circle"></i><br><?php echo htmlspecialchars(isset($timelinePanel['empty_state']) ? $timelinePanel['empty_state'] : 'No timeline events found.'); ?></div><?php } ?>
                        <?php foreach ($timeline as $event) { ?>
                            <?php $eventType = isset($event['type']) ? (string)$event['type'] : 'event'; ?>
                            <?php $eventSummary = isset($event['summary']) ? (string)$event['summary'] : 'Clinical event'; ?>
                            <?php
                                $severity = isset($event['severity_level']) && trim((string)$event['severity_level']) !== '' ? strtoupper((string)$event['severity_level']) : 'NORMAL';
                                if (!isset($event['severity_level']) || trim((string)$event['severity_level']) === '') {
                                    if (strtoupper($eventType) === 'MEDICATION') $severity = 'MEDICATION';
                                    elseif (strtoupper($eventType) === 'PROCEDURE') $severity = 'PROCEDURE';
                                    elseif (strtoupper($eventType) === 'IO') $severity = 'IO';
                                    elseif (stripos($eventSummary, 'critical') !== false) $severity = 'CRITICAL';
                                    elseif (stripos($eventSummary, 'attention') !== false) $severity = 'ATTENTION';
                                }
                                $severityLabelClass = 'success';
                                if (in_array($severity, array('CRITICAL', 'EMERGENCY'), true)) $severityLabelClass = 'danger';
                                elseif (in_array($severity, array('ATTENTION', 'HIGH', 'IO'), true)) $severityLabelClass = 'warning';
                                elseif (in_array($severity, array('MEDIUM', 'MEDICATION'), true)) $severityLabelClass = 'info';
                                elseif ($severity === 'PROCEDURE') $severityLabelClass = 'primary';
                            ?>
                            <div class="workspace-timeline-item <?php echo htmlspecialchars($eventType); ?>">
                                <span class="label label-default"><?php echo htmlspecialchars(strtoupper($eventType)); ?></span>
                                <span class="label label-<?php echo $severityLabelClass; ?>"><?php echo htmlspecialchars($severity); ?></span>
                                <strong><?php echo htmlspecialchars(isset($event['timestamp']) ? $event['timestamp'] : ''); ?></strong>
                                <?php if (isset($event['actor']) && trim((string)$event['actor']) !== '') { ?><span class="workspace-muted"> · <?php echo htmlspecialchars($event['actor']); ?></span><?php } ?>
                                <br>
                                <?php echo htmlspecialchars($eventSummary); ?>
                                <?php if (strtoupper($eventType) === 'PROCEDURE') { ?>
                                    <br>
                                    <span class="label label-success"><?php echo htmlspecialchars(isset($event['outcome_status']) ? $event['outcome_status'] : 'SUCCESS'); ?></span>
                                    <span class="label label-<?php echo isset($event['follow_up_required']) && $event['follow_up_required'] ? 'warning' : 'default'; ?>"><?php echo isset($event['follow_up_required']) && $event['follow_up_required'] ? 'Follow-up' : 'No follow-up'; ?></span>
                                <?php } ?>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </section>
        </aside>
    </div>
    <script src="<?php echo base_url();?>public/js/jquery.min.js"></script>
    <script src="<?php echo base_url();?>public/js/bootstrap.min.js" type="text/javascript"></script>
    <script src="<?php echo base_url();?>public/js/AdminLTE/app.js" type="text/javascript"></script>
    <script type="text/javascript">
        function nursingUuid(){
            return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
                var r = Math.random() * 16 | 0, v = c === 'x' ? r : (r & 0x3 | 0x8);
                return v.toString(16);
            });
        }
        function nursingEscape(value){
            return $('<div>').text(value === null || typeof value === 'undefined' ? '' : value).html();
        }
        function nursingSetBusy(busy){
            $('#nursingVitalsSaveBtn').prop('disabled', busy);
            if (busy) {
                $('#nursingVitalsSaveBtn').html('<i class="fa fa-spinner fa-spin"></i> Saving...');
            } else {
                $('#nursingVitalsSaveBtn').html('<i class="fa fa-save"></i> Save Vitals');
            }
        }
        function nursingSetNoteBusy(busy){
            $('#nursingNoteSaveBtn').prop('disabled', busy);
            if (busy) {
                $('#nursingNoteSaveBtn').html('<i class="fa fa-spinner fa-spin"></i> Saving...');
            } else {
                $('#nursingNoteSaveBtn').html('<i class="fa fa-save"></i> Save Note');
            }
        }
        function nursingRenderFeedback(payload){
            var html = '';
            if (payload && payload.status === 'success') {
                html += '<div class="alert alert-success"><i class="fa fa-check"></i> Vitals saved successfully.</div>';
            }
            if (payload && payload.errors && payload.errors.length > 0) {
                html += '<div class="alert alert-danger"><strong>Fix before saving:</strong><br>';
                for (var i = 0; i < payload.errors.length; i++) {
                    html += nursingEscape(payload.errors[i].message) + '<br>';
                }
                html += '</div>';
            }
            if (payload && payload.warnings && payload.warnings.length > 0) {
                html += '<div class="alert alert-warning"><strong>Please confirm:</strong><br>';
                for (var w = 0; w < payload.warnings.length; w++) {
                    html += nursingEscape(payload.warnings[w].message) + '<br>';
                }
                html += '</div>';
            }
            $('#nursingVitalsFeedback').html(html);
        }
        function nursingRenderNoteFeedback(payload){
            var html = '';
            if (payload && payload.status === 'success') {
                html += '<div class="alert alert-success"><i class="fa fa-check"></i> Note saved successfully.</div>';
            }
            if (payload && payload.errors && payload.errors.length > 0) {
                html += '<div class="alert alert-danger"><strong>Fix before saving:</strong><br>';
                for (var i = 0; i < payload.errors.length; i++) {
                    html += nursingEscape(payload.errors[i].message) + '<br>';
                }
                html += '</div>';
            }
            if (payload && payload.warnings && payload.warnings.length > 0) {
                html += '<div class="alert alert-warning"><strong>Please confirm:</strong><br>';
                for (var w = 0; w < payload.warnings.length; w++) {
                    html += nursingEscape(payload.warnings[w].message) + '<br>';
                }
                html += '</div>';
            }
            $('#nursingNoteFeedback').html(html);
        }
        function nursingRefreshPatient(summary){
            if (!summary || !summary.summary || !summary.summary.latest_vitals) {
                return;
            }
            var v = summary.summary.latest_vitals;
            $('#nursingVitalsRecordedAt').text(v.recorded_at || 'Not found');
            $('#nursingVitalsBp').text(v.bp || '');
            $('#nursingVitalsPulse').text(v.pulse || v.pulse_rate || '');
            $('#nursingVitalsTemp').text(v.temperature || '');
            $('#nursingVitalsResp').text(v.respiratory_rate || v.respiration || '');
        }
        function nursingNowLabel(){
            return 'Just now';
        }
        function nursingClearEmptyState(selector){
            $(selector).find('p.workspace-muted').first().remove();
        }
        function nursingPrependTimeline(type, summary){
            nursingClearEmptyState('#nursingPatientTimeline');
            var eventType = type || 'event';
            var severity = nursingTimelineSeverity(eventType, summary || '');
            var html = '<div class="workspace-timeline-item ' + nursingEscape(eventType) + '">';
            html += '<span class="label label-default">' + nursingEscape(eventType.toUpperCase()) + '</span> ';
            html += '<span class="label label-' + nursingEscape(nursingSeverityClass(severity)) + '">' + nursingEscape(severity) + '</span> ';
            html += '<strong>' + nursingEscape(nursingNowLabel()) + '</strong><br>';
            html += nursingEscape(summary || 'Clinical event');
            html += '</div>';
            $('#nursingPatientTimeline').prepend(html);
        }
        function nursingTimelineSeverity(type, summary){
            var eventType = String(type || '').toUpperCase();
            var text = String(summary || '').toLowerCase();
            if (eventType === 'MEDICATION') return 'MEDICATION';
            if (eventType === 'PROCEDURE') return 'PROCEDURE';
            if (text.indexOf('critical') !== -1) return 'CRITICAL';
            if (text.indexOf('attention') !== -1) return 'ATTENTION';
            return 'NORMAL';
        }
        function nursingSeverityClass(severity){
            if (severity === 'CRITICAL') return 'danger';
            if (severity === 'ATTENTION') return 'warning';
            if (severity === 'MEDICATION') return 'info';
            if (severity === 'PROCEDURE') return 'primary';
            return 'success';
        }
        function nursingPrependVitalsHistory(vitals){
            nursingClearEmptyState('#nursingVitalsHistory');
            var html = '<div class="workspace-action-box">';
            html += '<strong>' + nursingEscape(nursingNowLabel()) + '</strong><br>';
            html += 'BP: ' + nursingEscape(vitals.bp || '') + ' · ';
            html += 'Pulse: ' + nursingEscape(vitals.pulse || vitals.pulse_rate || '') + ' · ';
            html += 'Temp: ' + nursingEscape(vitals.temperature || '') + '<br>';
            html += 'Resp: ' + nursingEscape(vitals.respiratory_rate || vitals.respiration || '') + ' · ';
            html += 'SpO2: ' + nursingEscape(vitals.spo2 || '');
            html += '</div>';
            $('#nursingVitalsHistory').prepend(html);
        }
        function nursingVitalsSummary(vitals){
            var parts = [];
            if (vitals.bp) parts.push('BP ' + vitals.bp);
            if (vitals.temperature) parts.push('Temp ' + vitals.temperature);
            if (vitals.pulse || vitals.pulse_rate) parts.push('Pulse ' + (vitals.pulse || vitals.pulse_rate));
            if (vitals.respiratory_rate || vitals.respiration) parts.push('Resp ' + (vitals.respiratory_rate || vitals.respiration));
            if (vitals.spo2) parts.push('SpO2 ' + vitals.spo2);
            return parts.length > 0 ? parts.join(' · ') : 'Vitals recorded';
        }
        function nursingWorkspaceKey(suffix){
            return 'nursingWorkspace:' + <?php echo json_encode(isset($patient['encounter_id']) ? (string)$patient['encounter_id'] : ''); ?> + ':' + suffix;
        }
        function nursingStorageSet(key, value){
            try { sessionStorage.setItem(key, value); } catch (e) {}
        }
        function nursingStorageGet(key){
            try { return sessionStorage.getItem(key); } catch (e) { return null; }
        }
        function nursingStorageRemove(key){
            try { sessionStorage.removeItem(key); } catch (e) {}
        }
        function nursingShowSection(section){
            section = section || 'overview';
            $('.workspace-panel').removeClass('workspace-focus-card');
            $('.workspace-panel').closest('[class*="col-md-"]').removeClass('workspace-focus-column');
            $('[data-workspace-section]').each(function(){
                var sections = String($(this).data('workspace-section') || '').split(' ');
                $(this).toggleClass('workspace-section-hidden', $.inArray(section, sections) === -1);
            });
            if (section !== 'overview') {
                $('[data-workspace-section]:visible').each(function(){
                    if ($(this).hasClass('workspace-panel')) {
                        $(this).addClass('workspace-focus-card');
                        $(this).closest('[class*="col-md-"]').addClass('workspace-focus-column');
                    }
                });
            }
            $('.workspace-section-btn').removeClass('btn-primary').addClass('btn-default');
            $('.workspace-section-btn[data-section="' + section + '"]').removeClass('btn-default').addClass('btn-primary');
            nursingStorageSet(nursingWorkspaceKey('activeSection'), section);
        }
        function nursingCollectDraft(formSelector){
            var data = {};
            $(formSelector).find('input[name], textarea[name]').each(function(){
                var name = $(this).attr('name');
                if (name === 'idempotency_key' || $(this).attr('type') === 'hidden') return;
                data[name] = $(this).val();
            });
            return data;
        }
        function nursingApplyDraft(formSelector, key){
            var raw = nursingStorageGet(key);
            if (!raw) return;
            try {
                var data = JSON.parse(raw);
                for (var name in data) {
                    if (data.hasOwnProperty(name)) {
                        $(formSelector).find('[name="' + name + '"]').val(data[name]);
                    }
                }
            } catch (e) {}
        }
        function nursingBindDraft(formSelector, key){
            nursingApplyDraft(formSelector, key);
            $(formSelector).on('input change', 'input[name], textarea[name]', function(){
                nursingStorageSet(key, JSON.stringify(nursingCollectDraft(formSelector)));
            });
        }

        nursingShowSection(nursingStorageGet(nursingWorkspaceKey('activeSection')) || 'overview');
        <?php if ($workspaceWriteMode) { ?>
        $('#nursingIdempotencyKey').val(nursingUuid());
        $('#nursingNoteIdempotencyKey').val(nursingUuid());
        nursingBindDraft('#nursingVitalsForm', nursingWorkspaceKey('vitalsDraft'));
        nursingBindDraft('#nursingNoteForm', nursingWorkspaceKey('noteDraft'));
        <?php } ?>
        setTimeout(function(){
            var scrollTop = parseInt(nursingStorageGet(nursingWorkspaceKey('scrollTop')), 10);
            if (!isNaN(scrollTop)) $(window).scrollTop(scrollTop);
        }, 100);
        $('.workspace-section-btn').on('click', function(){
            nursingShowSection($(this).data('section'));
        });
        $(window).on('scroll', function(){
            nursingStorageSet(nursingWorkspaceKey('scrollTop'), String($(window).scrollTop()));
        });
        <?php if ($workspaceWriteMode) { ?>
        $('#nursingVitalsForm').on('submit', function(e){
            e.preventDefault();
            nursingSetBusy(true);
            $('#nursingVitalsFeedback').html('');
            var url = '<?php echo base_url();?>api/nursing/patient/<?php echo urlencode(isset($patient['encounter_id']) ? $patient['encounter_id'] : ''); ?>/vitals';
            $.ajax({
                url: url,
                type: 'POST',
                dataType: 'json',
                data: $(this).serialize()
            }).done(function(payload){
                nursingRenderFeedback(payload);
                if (payload && payload.status === 'success' && payload.patient_summary) {
                    nursingRefreshPatient(payload.patient_summary);
                    var vitals = payload.patient_summary.summary && payload.patient_summary.summary.latest_vitals ? payload.patient_summary.summary.latest_vitals : {};
                    nursingPrependVitalsHistory(vitals);
                    nursingPrependTimeline('vital', nursingVitalsSummary(vitals));
                    $('#nursingIdempotencyKey').val(nursingUuid());
                    nursingStorageRemove(nursingWorkspaceKey('vitalsDraft'));
                }
            }).fail(function(xhr){
                var payload = null;
                try { payload = xhr.responseJSON; } catch (e) {}
                if (!payload) {
                    payload = {status:'error', errors:[{message:'Unable to save vitals. Please try again.'}], warnings:[]};
                }
                nursingRenderFeedback(payload);
            }).always(function(){
                nursingSetBusy(false);
            });
        });
        $('#nursingNoteForm').on('submit', function(e){
            e.preventDefault();
            nursingSetNoteBusy(true);
            $('#nursingNoteFeedback').html('');
            var url = '<?php echo base_url();?>api/nursing/patient/<?php echo urlencode(isset($patient['encounter_id']) ? $patient['encounter_id'] : ''); ?>/notes';
            $.ajax({
                url: url,
                type: 'POST',
                dataType: 'json',
                data: $(this).serialize()
            }).done(function(payload){
                nursingRenderNoteFeedback(payload);
                if (payload && payload.status === 'success') {
                    var focus = $('[name="focus"]', '#nursingNoteForm').val();
                    var note = $('[name="note_text"]', '#nursingNoteForm').val();
                    var html = '<div class="workspace-action-box"><strong>Just now</strong> ';
                    if (focus) html += '<span class="label label-default">' + nursingEscape(focus) + '</span>';
                    html += '<br>' + nursingEscape(note) + '</div>';
                    nursingClearEmptyState('#nursingRecentNotes');
                    $('#nursingRecentNotes').prepend(html);
                    nursingPrependTimeline('note', (focus ? focus + ': ' : '') + note);
                    $('#nursingNoteForm')[0].reset();
                    $('#nursingNoteIdempotencyKey').val(nursingUuid());
                    nursingStorageRemove(nursingWorkspaceKey('noteDraft'));
                }
            }).fail(function(xhr){
                var payload = null;
                try { payload = xhr.responseJSON; } catch (e) {}
                if (!payload) {
                    payload = {status:'error', errors:[{message:'Unable to save note. Please try again.'}], warnings:[]};
                }
                nursingRenderNoteFeedback(payload);
            }).always(function(){
                nursingSetNoteBusy(false);
            });
        });
        <?php } ?>
    </script>
</body>
</html>
