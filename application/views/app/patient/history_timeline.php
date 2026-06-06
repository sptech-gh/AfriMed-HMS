<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Patient History — Hebrew Medical Center</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
    <style>
        :root{--ph-bg:#f4f6f9;--ph-card:#fff;--ph-border:#e3e6ec;--ph-shadow:0 2px 8px rgba(0,0,0,.07);--ph-radius:10px;--ph-green:#27ae60;--ph-yellow:#f39c12;--ph-blue:#2980b9;--ph-red:#e74c3c;--ph-gray:#95a5a6;}
        .ph-page{background:var(--ph-bg);min-height:100vh;}

        .ph-summary{background:var(--ph-card);border-radius:var(--ph-radius);box-shadow:var(--ph-shadow);padding:20px 24px;margin-bottom:20px;border-left:4px solid var(--ph-blue);}
        .ph-summary .ph-avatar{width:64px;height:64px;border-radius:50%;object-fit:cover;border:3px solid #ecf0f1;margin-right:16px;}
        .ph-summary .ph-patient-name{font-size:20px;font-weight:700;color:#2c3e50;margin:0;}
        .ph-summary .ph-patient-id{font-size:13px;color:var(--ph-gray);margin-top:2px;}
        .ph-stat-card{background:#f8f9fb;border-radius:8px;padding:12px 16px;text-align:center;border:1px solid var(--ph-border);}
        .ph-stat-card .ph-stat-val{font-size:22px;font-weight:700;color:#2c3e50;}
        .ph-stat-card .ph-stat-lbl{font-size:11px;color:var(--ph-gray);text-transform:uppercase;letter-spacing:.5px;}
        .ph-conditions{margin-top:8px;}
        .ph-conditions .label{font-size:11px;margin-right:4px;margin-bottom:4px;display:inline-block;font-weight:500;}

        .ph-filter-bar{background:var(--ph-card);border-radius:var(--ph-radius);box-shadow:var(--ph-shadow);padding:14px 18px;margin-bottom:18px;}
        .ph-filter-bar .form-control{font-size:13px;border-radius:6px;border:1px solid #dce1e8;}
        .ph-filter-bar label{font-size:11px;color:var(--ph-gray);text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px;}

        .ph-actions{margin-bottom:14px;display:flex;justify-content:space-between;align-items:center;}
        .ph-actions .ph-count{font-size:13px;color:var(--ph-gray);}

        .ph-tl{position:relative;padding-left:28px;}
        .ph-tl::before{content:'';position:absolute;left:12px;top:0;bottom:0;width:2px;background:#dce1e8;border-radius:1px;}

        .ph-card{background:var(--ph-card);border-radius:var(--ph-radius);box-shadow:var(--ph-shadow);padding:18px 20px;margin-bottom:14px;position:relative;transition:box-shadow .2s ease,transform .15s ease;cursor:default;border:1px solid var(--ph-border);}
        .ph-card:hover{box-shadow:0 4px 16px rgba(0,0,0,.1);transform:translateY(-1px);}
        .ph-card::before{content:'';position:absolute;left:-22px;top:24px;width:12px;height:12px;border-radius:50%;background:var(--ph-blue);border:2px solid var(--ph-card);z-index:1;}
        .ph-card.type-opd::before{background:var(--ph-blue);}
        .ph-card.type-ipd::before{background:var(--ph-green);}

        .ph-card-hdr{display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:8px;}
        .ph-card-dt{font-size:15px;font-weight:600;color:#2c3e50;}
        .ph-card-doc{font-size:13px;color:var(--ph-gray);margin-top:2px;}
        .ph-card-reason{margin-top:6px;font-size:13px;color:#555;}
        .ph-badges{margin-top:8px;display:flex;flex-wrap:wrap;gap:5px;}
        .ph-badge{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;color:#fff;}
        .ph-badge-vitals{background:var(--ph-blue);}
        .ph-badge-dx{background:#8e44ad;}
        .ph-badge-labs{background:var(--ph-yellow);}
        .ph-badge-imaging{background:var(--ph-green);}
        .ph-badge-rx{background:var(--ph-red);}
        .ph-badge-billing{background:var(--ph-gray);}
        .ph-badge-encounter{padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;}
        .ph-badge-opd{background:#d6eaf8;color:var(--ph-blue);}
        .ph-badge-ipd{background:#d5f5e3;color:var(--ph-green);}
        .ph-badge-status{padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;}
        .ph-status-complete{background:#d5f5e3;color:var(--ph-green);}
        .ph-status-pending{background:#fef9e7;color:var(--ph-yellow);}
        .ph-status-active{background:#d6eaf8;color:var(--ph-blue);}

        .ph-toggle-btn{border:none;background:var(--ph-blue);color:#fff;padding:6px 14px;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;transition:background .2s;}
        .ph-toggle-btn:hover{background:#1a6fa0;}
        .ph-toggle-btn:focus{outline:none;}

        .ph-details{margin-top:14px;border-top:1px solid var(--ph-border);padding-top:14px;display:none;}
        .ph-details .nav-tabs{border-bottom:2px solid #ecf0f1;}
        .ph-details .nav-tabs>li>a{font-size:12px;padding:8px 14px;border-radius:6px 6px 0 0;color:#7f8c8d;font-weight:600;}
        .ph-details .nav-tabs>li.active>a{color:var(--ph-blue);border-bottom-color:var(--ph-blue);}
        .ph-details .tab-content{padding:14px 4px;}
        .ph-details .table{font-size:13px;}
        .ph-details .table th{background:#f8f9fb;font-weight:600;font-size:12px;color:#555;}

        .ph-empty{color:var(--ph-gray);padding:30px;text-align:center;font-size:14px;}
        .ph-loadmore{text-align:center;padding:10px 0;}

        .ph-nurse-notice{background:#eaf6fd;border:1px solid #b8daff;border-radius:8px;padding:10px 16px;font-size:13px;color:#004085;margin-bottom:14px;}

        @media(max-width:991px){.ph-tl{padding-left:18px;}.ph-card::before{left:-14px;width:10px;height:10px;}}
        @media(max-width:768px){.ph-tl{padding-left:0;}.ph-tl::before{display:none;}.ph-card::before{display:none;}.ph-summary .row>[class^="col-"]{margin-bottom:10px;}}

        @media print{.ph-filter-bar,.ph-actions,.ph-toggle-btn,.no-print{display:none!important;}.ph-details{display:block!important;}}
    </style>
</head>
<body class="skin-blue">
    <?php require_once(APPPATH.'views/include/header.php');?>
    <div class="wrapper row-offcanvas row-offcanvas-left">
        <?php require_once(APPPATH.'views/include/sidebar.php');?>
        <aside class="right-side ph-page">
            <section class="content-header">
                <h1><i class="fa fa-history"></i> Patient History</h1>
                <ol class="breadcrumb">
                    <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                    <li><a href="<?php echo base_url()?>app/patient">Patient Master</a></li>
                    <li class="active">Patient History</li>
                </ol>
            </section>
            <section class="content">
<?php
    $patient_no = isset($patient_no) ? $patient_no : '';
    $pi = isset($patientInfo) ? $patientInfo : null;
    $pName = ($pi && isset($pi->name)) ? $pi->name : '';
    $pAge = ($pi && isset($pi->age)) ? $pi->age : '';
    $pGender = ($pi && isset($pi->gender)) ? $pi->gender : '';
    $pPicture = ($pi && isset($pi->picture) && $pi->picture) ? $pi->picture : 'avatar.png';
    $sm = isset($summary) ? $summary : array();
    $totalVisits = isset($sm['total_visits']) ? (int)$sm['total_visits'] : 0;
    $lastVisit = isset($sm['last_visit']) ? $sm['last_visit'] : '—';
    $conditions = isset($sm['active_conditions']) ? $sm['active_conditions'] : array();
    $fullAcc = (isset($full_access) && $full_access);
    $rc = isset($record_counts) ? $record_counts : array();
    $lv = isset($latest_vitals) ? $latest_vitals : null;
    $as = isset($allergy_summary) ? $allergy_summary : array();
    $mh = isset($medical_history) ? $medical_history : array();
    $aMeds = isset($active_medications) ? $active_medications : array();
?>
                <div class="ph-summary">
                    <div class="row">
                        <div class="col-md-5">
                            <div style="display:flex;align-items:center;">
                                <img src="<?php echo base_url();?>public/patient_picture/<?php echo htmlspecialchars($pPicture);?>" class="ph-avatar" alt="Patient">
                                <div>
                                    <p class="ph-patient-name"><?php echo htmlspecialchars($pName); ?></p>
                                    <p class="ph-patient-id"><i class="fa fa-id-card-o"></i> <?php echo htmlspecialchars($patient_no); ?> &nbsp;&bull;&nbsp; <?php echo htmlspecialchars($pAge); ?> yrs &nbsp;&bull;&nbsp; <?php echo htmlspecialchars($pGender); ?></p>
                                </div>
                            </div>
                            <?php if (!empty($conditions)) { ?>
                            <div class="ph-conditions">
                                <strong style="font-size:11px;color:#7f8c8d;">RECENT CONDITIONS:</strong><br>
                                <?php foreach ($conditions as $c) { ?>
                                    <span class="label label-warning"><?php echo htmlspecialchars($c); ?></span>
                                <?php } ?>
                            </div>
                            <?php } ?>
                        </div>
                        <div class="col-md-2">
                            <div class="ph-stat-card">
                                <div class="ph-stat-val"><?php echo $totalVisits; ?></div>
                                <div class="ph-stat-lbl">Total Visits</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="ph-stat-card">
                                <div class="ph-stat-val" style="font-size:16px;"><?php echo htmlspecialchars($lastVisit); ?></div>
                                <div class="ph-stat-lbl">Last Visit</div>
                            </div>
                        </div>
                        <div class="col-md-2" style="display:flex;flex-direction:column;gap:6px;">
                            <a class="btn btn-default btn-sm" href="<?php echo base_url();?>app/patient/view/<?php echo htmlspecialchars($patient_no); ?>"><i class="fa fa-user"></i> Profile</a>
                            <button class="btn btn-default btn-sm no-print" onclick="window.print()"><i class="fa fa-print"></i> Print</button>
                        </div>
                    </div>
                </div>

                <!-- Record Count Cards -->
                <div class="row" style="margin-bottom:18px;">
                    <div class="col-lg-2 col-xs-4">
                        <div class="ph-stat-card" style="border-left:3px solid var(--ph-blue);">
                            <div class="ph-stat-val" style="color:var(--ph-blue);"><?php echo isset($rc['vitals']) ? (int)$rc['vitals'] : 0; ?></div>
                            <div class="ph-stat-lbl"><i class="fa fa-heartbeat"></i> Vitals</div>
                        </div>
                    </div>
                    <div class="col-lg-2 col-xs-4">
                        <div class="ph-stat-card" style="border-left:3px solid var(--ph-yellow);">
                            <div class="ph-stat-val" style="color:var(--ph-yellow);"><?php echo isset($rc['labs']) ? (int)$rc['labs'] : 0; ?></div>
                            <div class="ph-stat-lbl"><i class="fa fa-flask"></i> Labs</div>
                        </div>
                    </div>
                    <div class="col-lg-2 col-xs-4">
                        <div class="ph-stat-card" style="border-left:3px solid var(--ph-green);">
                            <div class="ph-stat-val" style="color:var(--ph-green);"><?php echo isset($rc['imaging']) ? (int)$rc['imaging'] : 0; ?></div>
                            <div class="ph-stat-lbl"><i class="fa fa-camera"></i> Imaging</div>
                        </div>
                    </div>
                    <div class="col-lg-2 col-xs-4">
                        <div class="ph-stat-card" style="border-left:3px solid var(--ph-red);">
                            <div class="ph-stat-val" style="color:var(--ph-red);"><?php echo isset($rc['prescriptions']) ? (int)$rc['prescriptions'] : 0; ?></div>
                            <div class="ph-stat-lbl"><i class="fa fa-medkit"></i> Rx</div>
                        </div>
                    </div>
                    <div class="col-lg-2 col-xs-4">
                        <div class="ph-stat-card" style="border-left:3px solid #8e44ad;">
                            <div class="ph-stat-val" style="color:#8e44ad;"><?php echo isset($rc['diagnoses']) ? (int)$rc['diagnoses'] : 0; ?></div>
                            <div class="ph-stat-lbl"><i class="fa fa-stethoscope"></i> Diagnoses</div>
                        </div>
                    </div>
                    <div class="col-lg-2 col-xs-4">
                        <div class="ph-stat-card" style="border-left:3px solid var(--ph-gray);">
                            <div class="ph-stat-val" style="color:var(--ph-gray);"><?php echo isset($rc['invoices']) ? (int)$rc['invoices'] : 0; ?></div>
                            <div class="ph-stat-lbl"><i class="fa fa-money"></i> Invoices</div>
                        </div>
                    </div>
                </div>

                <!-- Medical Summary Panels -->
                <div class="row" style="margin-bottom:18px;">
                    <!-- Latest Vitals -->
                    <div class="col-md-6">
                        <div style="background:var(--ph-card);border-radius:var(--ph-radius);box-shadow:var(--ph-shadow);padding:16px 20px;border-left:4px solid var(--ph-blue);height:100%;">
                            <h4 style="margin:0 0 10px 0;font-size:14px;font-weight:700;color:#2c3e50;"><i class="fa fa-heartbeat" style="color:var(--ph-blue);"></i> Latest Vitals</h4>
                            <?php if ($lv) { ?>
                            <table class="table table-condensed" style="font-size:13px;margin-bottom:0;">
                                <tr>
                                    <td style="border-top:none;width:20%;"><strong>Date</strong></td>
                                    <td style="border-top:none;"><?php echo htmlspecialchars(isset($lv['dDateTime']) ? $lv['dDateTime'] : (isset($lv['date_visit']) ? $lv['date_visit'] : '')); ?></td>
                                    <td style="border-top:none;width:20%;"><strong>Pulse</strong></td>
                                    <td style="border-top:none;"><?php echo htmlspecialchars(isset($lv['pulse_rate']) ? $lv['pulse_rate'] : '—'); ?> bpm</td>
                                </tr>
                                <tr>
                                    <td><strong>Temp</strong></td>
                                    <td><?php echo htmlspecialchars(isset($lv['temperature']) ? $lv['temperature'] : '—'); ?> &deg;C</td>
                                    <td><strong>BP</strong></td>
                                    <td><?php echo htmlspecialchars(isset($lv['bp']) ? $lv['bp'] : '—'); ?> mmHg</td>
                                </tr>
                                <tr>
                                    <td><strong>Weight</strong></td>
                                    <td><?php echo htmlspecialchars(isset($lv['weight']) ? $lv['weight'] : '—'); ?> kg</td>
                                    <td><strong>Height</strong></td>
                                    <td><?php echo htmlspecialchars(isset($lv['height']) ? $lv['height'] : '—'); ?> cm</td>
                                </tr>
                                <tr>
                                    <td><strong>Resp</strong></td>
                                    <td><?php echo htmlspecialchars(isset($lv['respiration']) ? $lv['respiration'] : '—'); ?></td>
                                    <td><strong>SpO2</strong></td>
                                    <td><?php echo htmlspecialchars(isset($lv['spo2']) ? $lv['spo2'] : '—'); ?>%</td>
                                </tr>
                            </table>
                            <?php } else { ?>
                            <p style="color:var(--ph-gray);font-size:13px;margin:0;">No vitals recorded.</p>
                            <?php } ?>
                        </div>
                    </div>
                    <!-- Allergies & Warnings -->
                    <div class="col-md-6">
                        <div style="background:var(--ph-card);border-radius:var(--ph-radius);box-shadow:var(--ph-shadow);padding:16px 20px;border-left:4px solid var(--ph-red);height:100%;">
                            <h4 style="margin:0 0 10px 0;font-size:14px;font-weight:700;color:#2c3e50;"><i class="fa fa-exclamation-triangle" style="color:var(--ph-red);"></i> Allergies &amp; Warnings</h4>
                            <?php
                            $hasAllergies = !empty($as['allergies']);
                            $hasWarnings = !empty($as['warnings']);
                            ?>
                            <?php if ($hasAllergies) { ?>
                                <div style="margin-bottom:8px;">
                                    <strong style="font-size:12px;color:var(--ph-red);">ALLERGIES:</strong><br>
                                    <?php foreach ($as['allergies'] as $allergy) { ?>
                                        <span class="label label-danger" style="font-size:12px;margin:2px 4px 2px 0;display:inline-block;"><?php echo htmlspecialchars($allergy); ?></span>
                                    <?php } ?>
                                </div>
                            <?php } ?>
                            <?php if ($hasWarnings) { ?>
                                <div>
                                    <strong style="font-size:12px;color:var(--ph-yellow);">WARNINGS:</strong><br>
                                    <?php foreach ($as['warnings'] as $warning) { ?>
                                        <span class="label label-warning" style="font-size:12px;margin:2px 4px 2px 0;display:inline-block;"><?php echo htmlspecialchars($warning); ?></span>
                                    <?php } ?>
                                </div>
                            <?php } ?>
                            <?php if (!$hasAllergies && !$hasWarnings) { ?>
                                <p style="color:var(--ph-green);font-size:13px;margin:0;"><i class="fa fa-check-circle"></i> No known allergies or warnings.</p>
                            <?php } ?>
                        </div>
                    </div>
                </div>

                <?php if ($fullAcc) { ?>
                <div class="row" style="margin-bottom:18px;">
                    <!-- Active Medications -->
                    <div class="col-md-6">
                        <div style="background:var(--ph-card);border-radius:var(--ph-radius);box-shadow:var(--ph-shadow);padding:16px 20px;border-left:4px solid var(--ph-red);height:100%;">
                            <h4 style="margin:0 0 10px 0;font-size:14px;font-weight:700;color:#2c3e50;"><i class="fa fa-medkit" style="color:var(--ph-red);"></i> Recent Medications</h4>
                            <?php if (!empty($aMeds)) { ?>
                            <table class="table table-condensed table-striped" style="font-size:12px;margin-bottom:0;">
                                <thead><tr><th>Drug</th><th>Dosage</th><th>Days</th><th>Date</th></tr></thead>
                                <tbody>
                                <?php foreach ($aMeds as $med) { ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars(isset($med['drug_name']) && $med['drug_name'] ? $med['drug_name'] : (isset($med['medicine_text']) ? $med['medicine_text'] : '')); ?></strong></td>
                                    <td><?php echo htmlspecialchars(isset($med['dosage']) ? $med['dosage'] : ''); ?></td>
                                    <td><?php echo htmlspecialchars(isset($med['days']) ? $med['days'] : ''); ?></td>
                                    <td><?php echo htmlspecialchars(isset($med['dDate']) ? $med['dDate'] : ''); ?></td>
                                </tr>
                                <?php } ?>
                                </tbody>
                            </table>
                            <?php } else { ?>
                            <p style="color:var(--ph-gray);font-size:13px;margin:0;">No recent medications.</p>
                            <?php } ?>
                        </div>
                    </div>
                    <!-- Medical History -->
                    <div class="col-md-6">
                        <div style="background:var(--ph-card);border-radius:var(--ph-radius);box-shadow:var(--ph-shadow);padding:16px 20px;border-left:4px solid #8e44ad;height:100%;">
                            <h4 style="margin:0 0 10px 0;font-size:14px;font-weight:700;color:#2c3e50;"><i class="fa fa-book" style="color:#8e44ad;"></i> Medical History</h4>
                            <?php
                            $hasMH = false;
                            foreach ($mh as $v) { if (trim((string)$v) !== '') { $hasMH = true; break; } }
                            ?>
                            <?php if ($hasMH) { ?>
                            <table class="table table-condensed" style="font-size:13px;margin-bottom:0;">
                                <?php if (isset($mh['past_medical_history']) && $mh['past_medical_history'] !== '') { ?>
                                <tr><td style="width:35%;border-top:none;"><strong>Past Medical</strong></td><td style="border-top:none;"><?php echo htmlspecialchars($mh['past_medical_history']); ?></td></tr>
                                <?php } ?>
                                <?php if (isset($mh['family_history']) && $mh['family_history'] !== '') { ?>
                                <tr><td><strong>Family History</strong></td><td><?php echo htmlspecialchars($mh['family_history']); ?></td></tr>
                                <?php } ?>
                                <?php if (isset($mh['social_history']) && $mh['social_history'] !== '') { ?>
                                <tr><td><strong>Social History</strong></td><td><?php echo htmlspecialchars($mh['social_history']); ?></td></tr>
                                <?php } ?>
                                <?php if (isset($mh['personal_history']) && $mh['personal_history'] !== '') { ?>
                                <tr><td><strong>Personal History</strong></td><td><?php echo htmlspecialchars($mh['personal_history']); ?></td></tr>
                                <?php } ?>
                            </table>
                            <?php } else { ?>
                            <p style="color:var(--ph-gray);font-size:13px;margin:0;">No medical history recorded.</p>
                            <?php } ?>
                        </div>
                    </div>
                </div>
                <?php } ?>

                <?php if (!$fullAcc) { ?>
                <div class="ph-nurse-notice"><i class="fa fa-info-circle"></i> <strong>Limited Access:</strong> You can view vitals and basic visit info only. Clinical details are restricted to doctors and administrators.</div>
                <?php } ?>

                <div class="ph-filter-bar no-print">
                    <div class="row">
                        <div class="col-md-2">
                            <label>Date From</label>
                            <input type="date" id="phFrom" class="form-control input-sm">
                        </div>
                        <div class="col-md-2">
                            <label>Date To</label>
                            <input type="date" id="phTo" class="form-control input-sm">
                        </div>
                        <div class="col-md-2">
                            <label>Doctor</label>
                            <select id="phDoctor" class="form-control input-sm">
                                <option value="">All Doctors</option>
                                <?php if (isset($doctorList) && is_array($doctorList)) { foreach ($doctorList as $d) { ?>
                                    <option value="<?php echo htmlspecialchars($d->user_id); ?>"><?php echo htmlspecialchars($d->name); ?></option>
                                <?php }} ?>
                            </select>
                        </div>
                        <div class="col-md-1">
                            <label>Type</label>
                            <select id="phEncounter" class="form-control input-sm">
                                <option value="">All</option>
                                <option value="OPD">OPD</option>
                                <option value="IPD">IPD</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label>Record</label>
                            <select id="phType" class="form-control input-sm">
                                <option value="">All Records</option>
                                <option value="vitals">Vitals</option>
                                <option value="diagnosis">Diagnosis</option>
                                <option value="labs">Labs</option>
                                <option value="imaging">Imaging</option>
                                <option value="prescriptions">Prescriptions</option>
                                <option value="billing">Billing</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label>Search</label>
                            <input type="text" id="phQ" class="form-control input-sm" placeholder="Dx, drug, test...">
                        </div>
                        <div class="col-md-1" style="padding-top:20px;">
                            <button id="phApply" class="btn btn-primary btn-sm" style="width:100%;border-radius:6px;"><i class="fa fa-search"></i></button>
                        </div>
                    </div>
                </div>

                <div class="ph-actions no-print">
                    <div class="ph-count">
                        <span id="phCount"><i class="fa fa-spinner fa-spin"></i> Loading visits...</span>
                        &nbsp; <a href="#" id="phReset" style="font-size:12px;"><i class="fa fa-refresh"></i> Reset filters</a>
                    </div>
                </div>

                <div class="ph-tl" id="phTimeline"></div>
                <div id="phEmpty" class="ph-empty" style="display:none;">
                    <i class="fa fa-calendar-o" style="font-size:36px;color:#ddd;"></i><br><br>
                    No visits found for this patient.
                </div>
                <div id="phLoadMoreWrap" class="ph-loadmore" style="display:none;">
                    <button id="phLoadMore" class="btn btn-default"><i class="fa fa-arrow-down"></i> Load more visits</button>
                </div>

            </section>
        </aside>
    </div>

    <script src="<?php echo base_url();?>public/js/jquery.min.js"></script>
    <script src="<?php echo base_url();?>public/js/bootstrap.min.js" type="text/javascript"></script>
    <script src="<?php echo base_url();?>public/js/AdminLTE/app.js" type="text/javascript"></script>
    <script type="text/javascript">
    (function(){
        var patientNo = <?php echo json_encode((string)$patient_no); ?>;
        var baseUrl   = <?php echo json_encode((string)base_url()); ?>;
        var fullAccess = <?php echo json_encode((bool)$fullAcc); ?>;
        var offset = 0, limit = 20, total = 0, loading = false, cardSeq = 0;

        function esc(s){
            if(s===null||s===undefined) return '';
            return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
        }

        function statusClass(st){
            if(!st) return 'ph-status-pending';
            var s = String(st).toLowerCase();
            if(s==='completed'||s==='discharged'||s==='closed') return 'ph-status-complete';
            if(s==='active'||s==='admitted') return 'ph-status-active';
            return 'ph-status-pending';
        }

        function renderVisitCard(v){
            var seq = ++cardSeq;
            var dt = (v.date_visit||'') + (v.time_visit ? (' '+v.time_visit) : '');
            var enc = v.encounter_type||'';
            var encCls = enc.toUpperCase()==='IPD' ? 'ph-badge-ipd' : 'ph-badge-opd';
            var typeCls = enc.toUpperCase()==='IPD' ? 'type-ipd' : 'type-opd';
            var status = v.nStatus||'';

            var badges = '';
            if(parseInt(v.has_vitals,10)===1)        badges += '<span class="ph-badge ph-badge-vitals"><i class="fa fa-heartbeat"></i> Vitals</span>';
            if(fullAccess){
                if(parseInt(v.has_diagnosis,10)===1)  badges += '<span class="ph-badge ph-badge-dx"><i class="fa fa-stethoscope"></i> Dx</span>';
                if(parseInt(v.has_labs,10)===1)       badges += '<span class="ph-badge ph-badge-labs"><i class="fa fa-flask"></i> Labs</span>';
                if(parseInt(v.has_imaging,10)===1)    badges += '<span class="ph-badge ph-badge-imaging"><i class="fa fa-camera"></i> Imaging</span>';
                if(parseInt(v.has_prescriptions,10)===1) badges += '<span class="ph-badge ph-badge-rx"><i class="fa fa-medkit"></i> Rx</span>';
                if(parseInt(v.has_billing,10)===1)    badges += '<span class="ph-badge ph-badge-billing"><i class="fa fa-money"></i> Billing</span>';
            }

            var h = '';
            h += '<div class="ph-card '+typeCls+'" data-iop="'+esc(v.iop_id)+'" data-seq="'+seq+'">';
            h += '<div class="ph-card-hdr">';
            h += '  <div>';
            h += '    <div class="ph-card-dt"><i class="fa fa-calendar"></i> '+esc(dt)+'</div>';
            h += '    <div class="ph-card-doc"><i class="fa fa-user-md"></i> '+esc(v.doctor_name||'Unknown')+'</div>';
            h += '  </div>';
            h += '  <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">';
            h += '    <span class="ph-badge-encounter '+encCls+'">'+esc(enc)+'</span>';
            h += '    <span class="ph-badge-status '+statusClass(status)+'">'+esc(status||'Active')+'</span>';
            h += '    <button class="ph-toggle-btn" data-seq="'+seq+'"><i class="fa fa-chevron-down"></i> Details</button>';
            h += '  </div>';
            h += '</div>';
            if(v.complaints){
                h += '<div class="ph-card-reason"><strong>Chief Complaint:</strong> '+esc(v.complaints)+'</div>';
            }
            if(v.dept_name){
                h += '<div style="margin-top:4px;font-size:12px;color:#95a5a6;"><i class="fa fa-hospital-o"></i> '+esc(v.dept_name)+'</div>';
            }
            if(badges){
                h += '<div class="ph-badges">'+badges+'</div>';
            }
            h += '<div class="ph-details" id="phDet'+seq+'">';
            h += '  <div class="ph-details-inner"><i class="fa fa-spinner fa-spin"></i> Loading details...</div>';
            h += '</div>';
            h += '</div>';
            return h;
        }

        function renderDetails(data, fa, seq){
            var v = data.visit||{};
            var vitals = data.vitals||[];
            var notes = data.progress_notes||[];
            var dx = data.diagnoses||[];
            var rx = data.prescriptions||[];
            var labs = data.labs||[];
            var img = data.imaging||[];
            var billing = data.billing||{total:0,paid:0,outstanding:0,invoices:[]};
            var pfx = 'tab'+seq+'_';

            function tblVitals(){
                if(!vitals.length) return '<div class="ph-empty">No vitals recorded.</div>';
                var h='<table class="table table-condensed table-striped"><thead><tr><th>Date/Time</th><th>Pulse</th><th>Temp (&deg;C)</th><th>BP (mmHg)</th><th>Resp</th><th>Wt (kg)</th><th>Ht (cm)</th></tr></thead><tbody>';
                for(var i=0;i<vitals.length;i++){
                    var r=vitals[i];
                    h+='<tr><td>'+esc(r.dDateTime||r.dDate||'')+'</td><td>'+esc(r.pulse_rate||'')+'</td><td>'+esc(r.temperature||'')+'</td><td>'+esc(r.bp||'')+'</td><td>'+esc(r.respiration||'')+'</td><td>'+esc(r.weight||'')+'</td><td>'+esc(r.height||'')+'</td></tr>';
                }
                return h+'</tbody></table>';
            }
            function tblDx(){
                if(!fa) return '<div class="ph-nurse-notice"><i class="fa fa-lock"></i> Restricted to doctors.</div>';
                if(!dx.length) return '<div class="ph-empty">No diagnoses recorded.</div>';
                var h='<table class="table table-condensed table-striped"><thead><tr><th>Date</th><th>Diagnosis</th><th>Details</th><th>Remarks</th></tr></thead><tbody>';
                for(var i=0;i<dx.length;i++){var r=dx[i];h+='<tr><td>'+esc(r.dDate||'')+'</td><td><strong>'+esc(r.diagnosis_name||'')+'</strong></td><td>'+esc(r.diagnosis_text||'')+'</td><td>'+esc(r.remarks||'')+'</td></tr>';}
                return h+'</tbody></table>';
            }
            function tblNotes(){
                if(!fa) return '<div class="ph-nurse-notice"><i class="fa fa-lock"></i> Restricted to doctors.</div>';
                if(!notes.length) return '<div class="ph-empty">No consultation notes.</div>';
                var h='';
                for(var i=0;i<notes.length;i++){var r=notes[i];h+='<div style="background:#f8f9fb;border-radius:8px;padding:12px;margin-bottom:8px;border:1px solid #ecf0f1;"><div style="font-size:12px;color:#95a5a6;margin-bottom:6px;"><i class="fa fa-clock-o"></i> '+esc(r.dDateTime||'')+'</div><div><strong>Progress:</strong> '+esc(r.progress||'')+'</div><div><strong>Treatment:</strong> '+esc(r.treatment||'')+'</div>'+(r.remarks?'<div><strong>Remarks:</strong> '+esc(r.remarks)+'</div>':'')+'</div>';}
                return h;
            }
            function tblRx(){
                if(!fa) return '<div class="ph-nurse-notice"><i class="fa fa-lock"></i> Restricted to doctors.</div>';
                if(!rx.length) return '<div class="ph-empty">No prescriptions.</div>';
                var h='<table class="table table-condensed table-striped"><thead><tr><th>Drug</th><th>Dosage</th><th>Days</th><th>Qty</th><th>Instruction</th></tr></thead><tbody>';
                for(var i=0;i<rx.length;i++){
                    var r=rx[i];
                    var dose=(r.dosage||'');
                    if(r.unit){ dose += (dose?' ':'') + r.unit; }
                    if(!dose && r.frequency){ dose = r.frequency; }
                    h+='<tr><td><strong>'+esc(r.drug_name||r.medicine_text||'')+'</strong></td><td>'+esc(dose)+'</td><td>'+esc(r.days||'')+'</td><td>'+esc(r.total_qty||'')+'</td><td>'+esc(r.instruction||'')+'</td></tr>';
                }
                return h+'</tbody></table>';
            }
            function tblLab(rows){
                if(!fa) return '<div class="ph-nurse-notice"><i class="fa fa-lock"></i> Restricted to doctors.</div>';
                if(!rows.length) return '<div class="ph-empty">No items.</div>';
                var h='<table class="table table-condensed table-striped"><thead><tr><th>Date/Time</th><th>Item</th><th>Status</th><th>Findings</th><th>Result</th><th>File</th></tr></thead><tbody>';
                for(var i=0;i<rows.length;i++){
                    var r=rows[i];var item=r.display_name||r.particular_name||r.sono_item_name||r.laboratory_text||'';
                    var wf=r.wf_status||'';
                    var wfCls=wf.toLowerCase()==='completed'||wf.toLowerCase()==='delivered'?'color:'+('var(--ph-green)'):(wf.toLowerCase()==='pending'?'color:var(--ph-yellow)':'color:var(--ph-blue)');
                    var isPending = String(r.findings||'')==='PENDING CONSOLIDATED RELEASE' || String(r.result||'')==='PENDING CONSOLIDATED RELEASE';
                    var att = (!isPending && r.lab_result_upload && r.io_lab_id) ? '<a target="_blank" href="'+baseUrl+'app/laboratory/download_result/'+encodeURIComponent(r.io_lab_id)+'" class="btn btn-xs btn-default"><i class="fa fa-download"></i></a>' : '';
                    h+='<tr><td>'+esc(r.dDateTime||'')+'</td><td>'+esc(item)+'</td><td style="'+wfCls+';font-weight:600;">'+esc(wf)+'</td><td>'+esc(r.findings||'')+'</td><td>'+esc(r.result||'')+'</td><td>'+att+'</td></tr>';
                }
                return h+'</tbody></table>';
            }
            function tblBilling(){
                if(!fa) return '<div class="ph-nurse-notice"><i class="fa fa-lock"></i> Restricted.</div>';
                var inv=billing.invoices||[];
                var tx=billing.transactions||[];
                var sum='<div class="row" style="margin-bottom:12px;">'
                    +'<div class="col-sm-4"><div class="ph-stat-card" style="border-left:3px solid var(--ph-blue);text-align:left;"><div class="ph-stat-lbl">Total</div><div class="ph-stat-val" style="font-size:18px;">GHS '+esc(Number(billing.total).toFixed(2))+'</div></div></div>'
                    +'<div class="col-sm-4"><div class="ph-stat-card" style="border-left:3px solid var(--ph-green);text-align:left;"><div class="ph-stat-lbl">Paid</div><div class="ph-stat-val" style="font-size:18px;color:var(--ph-green);">GHS '+esc(Number(billing.paid).toFixed(2))+'</div></div></div>'
                    +'<div class="col-sm-4"><div class="ph-stat-card" style="border-left:3px solid var(--ph-red);text-align:left;"><div class="ph-stat-lbl">Outstanding</div><div class="ph-stat-val" style="font-size:18px;color:var(--ph-red);">GHS '+esc(Number(billing.outstanding).toFixed(2))+'</div></div></div>'
                    +'</div>';
                if(!inv.length && tx.length){
                    var h2=sum+'<table class="table table-condensed table-striped"><thead><tr><th>Invoice</th><th>Dept</th><th>Item</th><th>Status</th><th>Amount</th><th>Paid</th><th>Balance</th></tr></thead><tbody>';
                    for(var j=0;j<tx.length;j++){
                        var t=tx[j];
                        h2+='<tr><td>'+esc(t.invoice_no||'')+'</td><td>'+esc(t.department||'')+'</td><td>'+esc(t.item_name||'')+'</td><td>'+esc(t.payment_status||'')+'</td><td>'+esc(t.net_amount||'')+'</td><td>'+esc(t.paid_amount||'')+'</td><td>'+esc(t.balance_amount||'')+'</td></tr>';
                    }
                    return h2+'</tbody></table>';
                }
                if(!inv.length) return sum+'<div class="ph-empty">No invoices.</div>';
                var h=sum+'<table class="table table-condensed table-striped"><thead><tr><th>Invoice</th><th>Date</th><th>Payment</th><th>Total</th><th>Paid</th><th>Balance</th></tr></thead><tbody>';
                for(var i=0;i<inv.length;i++){var r=inv[i];h+='<tr><td>'+esc(r.invoice_no||'')+'</td><td>'+esc(r.dDate||'')+'</td><td>'+esc(r.payment_type||'')+'</td><td>'+esc(r.total_amount||'')+'</td><td>'+esc(r.paid_amount||'')+'</td><td>'+esc(r.outstanding_amount||'')+'</td></tr>';}
                return h+'</tbody></table>';
            }

            var ov='<div class="row"><div class="col-sm-6"><table class="table table-condensed table-striped">'
                +'<tr><th style="width:40%;">Visit ID</th><td>'+esc(v.IO_ID||'')+' <span class="ph-badge-encounter '+(String(v.patient_type||'').toUpperCase()==='IPD'?'ph-badge-ipd':'ph-badge-opd')+'">'+esc(v.patient_type||'')+'</span></td></tr>'
                +'<tr><th>Date/Time</th><td>'+esc((v.date_visit||'')+' '+(v.time_visit||''))+'</td></tr>'
                +'<tr><th>Doctor</th><td>'+esc(v.doctor_name||'')+'</td></tr>'
                +'<tr><th>Department</th><td>'+esc(v.dept_name||'')+'</td></tr>'
                +'<tr><th>Status</th><td><span class="ph-badge-status '+statusClass(v.nStatus)+'">'+esc(v.nStatus||'Active')+'</span></td></tr>'
                +'</table></div><div class="col-sm-6"><table class="table table-condensed table-striped">'
                +'<tr><th style="width:40%;">Complaints</th><td>'+esc(v.complaints||'—')+'</td></tr>'
                +'<tr><th>Provisional Dx</th><td>'+esc(v.provisional_diagnosis||'—')+'</td></tr>'
                +'<tr><th>Allergies</th><td style="'+(v.allergies?'color:var(--ph-red);font-weight:600;':'')+'">'+esc(v.allergies||'None')+'</td></tr>'
                +'<tr><th>Warnings</th><td style="'+(v.warnings?'color:var(--ph-red);font-weight:600;':'')+'">'+esc(v.warnings||'None')+'</td></tr>'
                +'</table></div></div>';

            var html='<ul class="nav nav-tabs" role="tablist">'
                +'<li class="active"><a href="#'+pfx+'ov" data-toggle="tab"><i class="fa fa-list-alt"></i> Overview</a></li>'
                +'<li><a href="#'+pfx+'vt" data-toggle="tab"><i class="fa fa-heartbeat"></i> Vitals</a></li>'
                +(fa?'<li><a href="#'+pfx+'cn" data-toggle="tab"><i class="fa fa-file-text-o"></i> Notes</a></li>':'')
                +(fa?'<li><a href="#'+pfx+'dx" data-toggle="tab"><i class="fa fa-stethoscope"></i> Dx</a></li>':'')
                +(fa?'<li><a href="#'+pfx+'lb" data-toggle="tab"><i class="fa fa-flask"></i> Labs</a></li>':'')
                +(fa?'<li><a href="#'+pfx+'im" data-toggle="tab"><i class="fa fa-camera"></i> Imaging</a></li>':'')
                +(fa?'<li><a href="#'+pfx+'rx" data-toggle="tab"><i class="fa fa-medkit"></i> Rx</a></li>':'')
                +(fa?'<li><a href="#'+pfx+'bl" data-toggle="tab"><i class="fa fa-money"></i> Billing</a></li>':'')
                +'</ul>'
                +'<div class="tab-content">'
                +'<div class="tab-pane active" id="'+pfx+'ov">'+ov+'</div>'
                +'<div class="tab-pane" id="'+pfx+'vt">'+tblVitals()+'</div>'
                +(fa?'<div class="tab-pane" id="'+pfx+'cn">'+tblNotes()+'</div>':'')
                +(fa?'<div class="tab-pane" id="'+pfx+'dx">'+tblDx()+'</div>':'')
                +(fa?'<div class="tab-pane" id="'+pfx+'lb">'+tblLab(labs)+'</div>':'')
                +(fa?'<div class="tab-pane" id="'+pfx+'im">'+tblLab(img)+'</div>':'')
                +(fa?'<div class="tab-pane" id="'+pfx+'rx">'+tblRx()+'</div>':'')
                +(fa?'<div class="tab-pane" id="'+pfx+'bl">'+tblBilling()+'</div>':'')
                +'</div>';
            return html;
        }

        function currentFilters(){
            return {
                from:$('#phFrom').val()||'',
                to:$('#phTo').val()||'',
                doctor:$('#phDoctor').val()||'',
                encounter:$('#phEncounter').val()||'',
                type:$('#phType').val()||'',
                q:$('#phQ').val()||''
            };
        }

        function updateCount(){
            if(total===0) $('#phCount').html('<span style="color:#95a5a6;">No visits found</span>');
            else $('#phCount').html('Showing <strong>'+Math.min(offset,total)+'</strong> of <strong>'+total+'</strong> visits');
        }

        function fetchVisits(reset){
            if(loading) return;
            loading=true;
            if(reset){offset=0;$('#phTimeline').html('');$('#phEmpty').hide();$('#phLoadMoreWrap').hide();$('#phCount').html('<i class="fa fa-spinner fa-spin"></i> Loading...');}
            var f=currentFilters();
            $.getJSON(baseUrl+'app/patient_history/visits_json/'+encodeURIComponent(patientNo),{
                limit:limit,offset:offset,from:f.from,to:f.to,doctor:f.doctor,encounter:f.encounter,type:f.type,q:f.q
            }).done(function(resp){
                if(!resp||!resp.ok){$('#phCount').html('<span style="color:var(--ph-red);">Error loading data</span>');loading=false;return;}
                total=parseInt(resp.total,10)||0;
                var rows=resp.rows||[];
                if(reset&&rows.length===0){$('#phEmpty').show();}
                for(var i=0;i<rows.length;i++){$('#phTimeline').append(renderVisitCard(rows[i]));}
                offset+=rows.length;
                updateCount();
                if(offset<total){$('#phLoadMoreWrap').show();}else{$('#phLoadMoreWrap').hide();}
                loading=false;
            }).fail(function(xhr){
                var msg='Error loading data';
                if(xhr&&xhr.status===403) msg='Access denied';
                $('#phCount').html('<span style="color:var(--ph-red);">'+msg+'</span>');
                loading=false;
            });
        }

        function fetchDetails(card){
            var iop=$(card).data('iop');
            var seq=$(card).data('seq');
            var $inner=$(card).find('.ph-details-inner');
            $.getJSON(baseUrl+'app/patient_history/visit_json/'+encodeURIComponent(iop)).done(function(resp){
                if(!resp||!resp.ok){$inner.html('<div style="color:var(--ph-red);padding:10px;"><i class="fa fa-exclamation-triangle"></i> Failed to load details.</div>');return;}
                $inner.html(renderDetails(resp.data,resp.full_access,seq));
            }).fail(function(){
                $inner.html('<div style="color:var(--ph-red);padding:10px;"><i class="fa fa-exclamation-triangle"></i> Failed to load details.</div>');
            });
        }

        $(document).on('click','.ph-toggle-btn',function(e){
            e.preventDefault();
            var card=$(this).closest('.ph-card');
            var $det=card.find('.ph-details');
            if($det.is(':visible')){
                $det.slideUp(200);
                $(this).html('<i class="fa fa-chevron-down"></i> Details');
            } else {
                $det.slideDown(200);
                $(this).html('<i class="fa fa-chevron-up"></i> Hide');
                if(!card.data('loaded')){
                    card.data('loaded','1');
                    fetchDetails(card);
                }
            }
        });

        $('#phLoadMore').on('click',function(){fetchVisits(false);});
        $('#phApply').on('click',function(){fetchVisits(true);});
        $('#phReset').on('click',function(e){
            e.preventDefault();
            $('#phFrom,#phTo,#phQ').val('');
            $('#phDoctor,#phEncounter,#phType').val('');
            fetchVisits(true);
        });
        $('#phQ').on('keypress',function(e){if(e.which===13){e.preventDefault();fetchVisits(true);}});

        fetchVisits(true);
    })();
    </script>
</body>
</html>
