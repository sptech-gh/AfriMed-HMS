<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Doctor Report Hub</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <link href="<?php echo base_url() ?>public/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo base_url() ?>public/css/font-awesome.min.css" rel="stylesheet">
    <link href="<?php echo base_url() ?>public/css/AdminLTE.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo base_url() ?>public/datepicker/css/datepicker.css">
    <style>
        /* ── Stat cards ───────────────────────────────────────────── */
        .rh-stat-card {
            border-radius: 6px;
            padding: 18px 20px;
            color: #fff;
            margin-bottom: 20px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,.12);
        }
        .rh-stat-card .rh-icon {
            position: absolute; right: 16px; top: 14px;
            font-size: 48px; opacity: .18;
        }
        .rh-stat-card .rh-val  { font-size: 32px; font-weight: 700; line-height: 1; }
        .rh-stat-card .rh-lbl  { font-size: 13px; margin-top: 4px; opacity: .88; }
        .rh-stat-card .rh-sub  { font-size: 11px; margin-top: 6px; opacity: .75; }
        .bg-teal   { background: #00897b; }
        .bg-navy   { background: #1a237e; }
        .bg-olive  { background: #558b2f; }
        .bg-orange { background: #e65100; }
        .bg-purple { background: #6a1b9a; }
        .bg-blue   { background: #1565c0; }
        .bg-red    { background: #c62828; }
        .bg-slate  { background: #37474f; }

        /* ── Section boxes ────────────────────────────────────────── */
        .rh-box { border-radius: 6px; margin-bottom: 24px; box-shadow: 0 1px 6px rgba(0,0,0,.08); }
        .rh-box .rh-box-header {
            padding: 12px 18px; border-bottom: 1px solid #e5e5e5;
            font-weight: 600; font-size: 14px; background: #f8f9fa;
            border-radius: 6px 6px 0 0; color: #333;
        }
        .rh-box .rh-box-header i { margin-right: 7px; }
        .rh-box .rh-box-body  { padding: 16px 18px; background:#fff; border-radius: 0 0 6px 6px; }

        /* ── GHS compliance badge ─────────────────────────────────── */
        .ghs-badge {
            display: inline-block; font-size: 10px; padding: 2px 7px;
            border-radius: 3px; background: #e8f5e9; color: #2e7d32;
            border: 1px solid #a5d6a7; vertical-align: middle; margin-left: 6px;
        }
        .nhis-badge {
            display: inline-block; font-size: 10px; padding: 2px 7px;
            border-radius: 3px; background: #e3f2fd; color: #1565c0;
            border: 1px solid #90caf9; vertical-align: middle; margin-left: 4px;
        }

        /* ── Bar chart (CSS-only) ─────────────────────────────────── */
        .rh-bar-wrap { margin-bottom: 8px; }
        .rh-bar-label { font-size: 12px; color: #555; margin-bottom: 2px;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%; }
        .rh-bar-track { background: #eceff1; border-radius: 3px; height: 14px; position: relative; }
        .rh-bar-fill  { height: 100%; border-radius: 3px; transition: width .4s ease; }
        .rh-bar-val   { position: absolute; right: 6px; top: 0; font-size: 11px;
            line-height: 14px; color: #fff; font-weight: 600; }

        /* ── Trend sparkline ──────────────────────────────────────── */
        .rh-trend-grid { display: flex; align-items: flex-end; gap: 6px; height: 80px; }
        .rh-trend-col  { flex: 1; display: flex; flex-direction: column; align-items: center; }
        .rh-trend-bar  { width: 100%; background: #1565c0; border-radius: 3px 3px 0 0;
            min-height: 4px; transition: height .3s; }
        .rh-trend-day  { font-size: 9px; color: #90a4ae; margin-top: 3px; }
        .rh-trend-cnt  { font-size: 10px; color: #546e7a; font-weight: 600; }

        /* ── Payer donut (CSS) ────────────────────────────────────── */
        .rh-payer-bar { height: 18px; border-radius: 9px; overflow: hidden;
            display: flex; margin-bottom: 8px; }
        .rh-payer-nhis { background: #1565c0; }
        .rh-payer-cash { background: #78909c; }
        .rh-payer-legend { display: flex; gap: 16px; font-size: 12px; }
        .rh-payer-dot { width: 10px; height: 10px; border-radius: 50%;
            display: inline-block; margin-right: 4px; vertical-align: middle; }

        /* ── Date filter form ─────────────────────────────────────── */
        .rh-filter-bar { background: #eceff1; border-radius: 6px;
            padding: 10px 16px; margin-bottom: 20px; }
        .rh-filter-bar label { font-size: 12px; font-weight: 600; color: #546e7a; }

        /* ── Print ────────────────────────────────────────────────── */
        @media print {
            .sidebar-offcanvas, .content-header, .rh-filter-bar,
            .btn, form { display: none !important; }
            .rh-stat-card { color: #000 !important; background: #f5f5f5 !important; }
        }
    </style>
</head>
<body class="skin-blue">

<?php require_once(APPPATH . 'views/include/header.php'); ?>

<div class="wrapper row-offcanvas row-offcanvas-left">
    <?php require_once(APPPATH . 'views/include/sidebar.php'); ?>

    <aside class="right-side">
        <section class="content-header">
            <h1>
                Doctor Report Hub
                <small>GHS &amp; NHIS Compliant</small>
            </h1>
            <ol class="breadcrumb">
                <li><a href="<?php echo base_url() ?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                <li><a href="<?php echo base_url() ?>app/doctor/opd">Doctor</a></li>
                <li class="active">Report Hub</li>
            </ol>
        </section>

        <section class="content">

            <?php echo $message; ?>

            <!-- ── DATE FILTER ─────────────────────────────────────── -->
            <div class="rh-filter-bar">
                <form method="get" action="" class="form-inline">
                    <label>From&nbsp;</label>
                    <input type="text" name="date_from" id="rh_from"
                           class="form-control input-sm" style="width:130px;margin-right:10px;"
                           value="<?php echo htmlspecialchars($date_from); ?>" autocomplete="off">
                    <label>To&nbsp;</label>
                    <input type="text" name="date_to" id="rh_to"
                           class="form-control input-sm" style="width:130px;margin-right:10px;"
                           value="<?php echo htmlspecialchars($date_to); ?>" autocomplete="off">
                    <button class="btn btn-sm btn-primary" type="submit">
                        <i class="fa fa-search"></i> Apply
                    </button>
                    <a href="<?php echo base_url() ?>app/doctor/report_hub?date_from=<?php echo date('Y-m-01'); ?>&date_to=<?php echo date('Y-m-d'); ?>"
                       class="btn btn-sm btn-default" style="margin-left:6px;">
                        <i class="fa fa-refresh"></i> This Month
                    </a>
                    <a href="<?php echo base_url() ?>app/doctor/report_hub?date_from=<?php echo date('Y-m-d'); ?>&date_to=<?php echo date('Y-m-d'); ?>"
                       class="btn btn-sm btn-default" style="margin-left:4px;">
                        <i class="fa fa-calendar"></i> Today
                    </a>
                    <button onclick="window.print()" type="button"
                            class="btn btn-sm btn-default pull-right" style="margin-left:6px;">
                        <i class="fa fa-print"></i> Print
                    </button>
                    <span class="text-muted pull-right" style="font-size:11px;line-height:30px;">
                        <span class="ghs-badge">GHS DHIMS2</span>
                        <span class="nhis-badge">NHIS</span>
                    </span>
                </form>
            </div>

            <?php
            $att  = $stats['attendance'];
            $pay  = $stats['payer'];
            $lab  = $stats['labs'];
            $rx   = $stats['rx'];
            $adm  = $stats['admissions'];
            $avg  = $stats['avg_consult_min'];
            $total = (int)$att->total_visits ?: 1; // avoid /0
            ?>

            <!-- ── ROW 1: KEY METRICS ─────────────────────────────── -->
            <div class="row">
                <div class="col-md-3 col-sm-6">
                    <div class="rh-stat-card bg-teal">
                        <i class="fa fa-users rh-icon"></i>
                        <div class="rh-val"><?php echo (int)$att->total_visits; ?></div>
                        <div class="rh-lbl">Total OPD Visits</div>
                        <div class="rh-sub">
                            <?php echo (int)$att->male_count; ?> M &nbsp;|&nbsp;
                            <?php echo (int)$att->female_count; ?> F
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="rh-stat-card bg-navy">
                        <i class="fa fa-check-circle rh-icon"></i>
                        <div class="rh-val"><?php echo (int)$att->completed; ?></div>
                        <div class="rh-lbl">Consultations Completed</div>
                        <div class="rh-sub"><?php echo (int)$att->active; ?> still active</div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="rh-stat-card bg-blue">
                        <i class="fa fa-flask rh-icon"></i>
                        <div class="rh-val"><?php echo (int)$lab->total_labs; ?></div>
                        <div class="rh-lbl">Lab Requests</div>
                        <div class="rh-sub"><?php echo (int)$lab->completed_labs; ?> results returned</div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="rh-stat-card bg-olive">
                        <i class="fa fa-medkit rh-icon"></i>
                        <div class="rh-val"><?php echo (int)$rx->total_rx; ?></div>
                        <div class="rh-lbl">Prescriptions Issued</div>
                        <div class="rh-sub"><?php echo (int)$rx->dispensed_rx; ?> dispensed</div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-3 col-sm-6">
                    <div class="rh-stat-card bg-purple">
                        <i class="fa fa-child rh-icon"></i>
                        <div class="rh-val"><?php echo (int)$att->under5; ?></div>
                        <div class="rh-lbl">Under-5 Visits <span class="ghs-badge">GHS</span></div>
                        <div class="rh-sub"><?php echo (int)$att->over5; ?> aged 5+</div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="rh-stat-card bg-orange">
                        <i class="fa fa-bed rh-icon"></i>
                        <div class="rh-val"><?php echo (int)$adm->admitted_count; ?></div>
                        <div class="rh-lbl">Admitted / Referred</div>
                        <div class="rh-sub">from OPD this period</div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="rh-stat-card bg-slate">
                        <i class="fa fa-clock-o rh-icon"></i>
                        <div class="rh-val"><?php echo $avg > 0 ? number_format($avg, 1) : '—'; ?></div>
                        <div class="rh-lbl">Avg Consult Time (min)</div>
                        <div class="rh-sub">OPD workflow data</div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="rh-stat-card bg-red">
                        <i class="fa fa-shield rh-icon"></i>
                        <div class="rh-val"><?php echo (int)$pay->nhis_count; ?></div>
                        <div class="rh-lbl">NHIS Patients <span class="nhis-badge">NHIS</span></div>
                        <div class="rh-sub"><?php echo (int)$pay->cash_count; ?> cash / private</div>
                    </div>
                </div>
            </div>

            <!-- ── ROW 2: DAILY TREND + PAYER SPLIT ──────────────── -->
            <div class="row">
                <!-- 7-day Trend -->
                <div class="col-md-7">
                    <div class="rh-box">
                        <div class="rh-box-header">
                            <i class="fa fa-area-chart text-blue"></i>
                            7-Day Patient Trend
                            <span class="ghs-badge">GHS Daily Returns</span>
                        </div>
                        <div class="rh-box-body">
                            <?php
                            // Build last-7-days lookup
                            $trendMap = array();
                            if (is_array($stats['daily_trend'])) {
                                foreach ($stats['daily_trend'] as $td) {
                                    $trendMap[$td->visit_day] = (int)$td->daily_count;
                                }
                            }
                            $maxTrend = $trendMap ? max($trendMap) : 1;
                            $days7 = array();
                            for ($d = 6; $d >= 0; $d--) {
                                $days7[] = date('Y-m-d', strtotime("-{$d} days"));
                            }
                            ?>
                            <div class="rh-trend-grid">
                                <?php foreach ($days7 as $day): ?>
                                <?php
                                $cnt = isset($trendMap[$day]) ? $trendMap[$day] : 0;
                                $pct = $maxTrend > 0 ? round(($cnt / $maxTrend) * 100) : 0;
                                ?>
                                <div class="rh-trend-col">
                                    <div class="rh-trend-cnt"><?php echo $cnt ?: ''; ?></div>
                                    <div class="rh-trend-bar" style="height:<?php echo max(4, $pct * 0.7); ?>px;
                                        background:<?php echo (date('Y-m-d') === $day) ? '#e53935' : '#1565c0'; ?>;"></div>
                                    <div class="rh-trend-day"><?php echo date('D', strtotime($day)); ?><br><?php echo date('d', strtotime($day)); ?></div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <p class="text-muted" style="font-size:11px;margin-top:8px;">
                                <i class="fa fa-circle text-red"></i> Today &nbsp;
                                <i class="fa fa-circle text-blue"></i> Previous days
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Payer Split + Visit Types -->
                <div class="col-md-5">
                    <div class="rh-box">
                        <div class="rh-box-header">
                            <i class="fa fa-pie-chart text-purple"></i>
                            Payer &amp; Visit Type Split
                            <span class="nhis-badge">NHIS</span>
                        </div>
                        <div class="rh-box-body">
                            <?php
                            $nhis = (int)$pay->nhis_count;
                            $cash = (int)$pay->cash_count;
                            $payTotal = ($nhis + $cash) ?: 1;
                            $nhisPct = round(($nhis / $payTotal) * 100);
                            $cashPct = 100 - $nhisPct;
                            ?>
                            <p style="font-size:12px;font-weight:600;margin-bottom:6px;">Insurance vs Cash</p>
                            <div class="rh-payer-bar">
                                <div class="rh-payer-nhis" style="width:<?php echo $nhisPct; ?>%;"></div>
                                <div class="rh-payer-cash" style="width:<?php echo $cashPct; ?>%;"></div>
                            </div>
                            <div class="rh-payer-legend">
                                <span><span class="rh-payer-dot" style="background:#1565c0;"></span>NHIS <?php echo $nhisPct; ?>% (<?php echo $nhis; ?>)</span>
                                <span><span class="rh-payer-dot" style="background:#78909c;"></span>Cash <?php echo $cashPct; ?>% (<?php echo $cash; ?>)</span>
                            </div>
                            <hr style="margin:10px 0;">
                            <p style="font-size:12px;font-weight:600;margin-bottom:6px;">Visit Types</p>
                            <?php
                            $vtLabels = array(
                                'FIRST_VISIT'        => 'New Patient',
                                'REVIEW'             => 'Review',
                                'FOLLOW_UP'          => 'Follow-Up',
                                'WALK_IN'            => 'Walk-In',
                                'MISSED_APPOINTMENT' => 'Missed Appt',
                                'EMERGENCY'          => 'Emergency',
                            );
                            $vtColors = array(
                                'FIRST_VISIT'        => '#1565c0',
                                'REVIEW'             => '#2e7d32',
                                'FOLLOW_UP'          => '#6a1b9a',
                                'WALK_IN'            => '#37474f',
                                'MISSED_APPOINTMENT' => '#e65100',
                                'EMERGENCY'          => '#c62828',
                            );
                            if (!empty($stats['visit_types'])):
                                $vtMax = max(array_map(function($v){ return (int)$v->cnt; }, $stats['visit_types'])) ?: 1;
                                foreach ($stats['visit_types'] as $vt):
                                    $vtLbl = isset($vtLabels[$vt->visit_type]) ? $vtLabels[$vt->visit_type] : str_replace('_',' ',$vt->visit_type);
                                    $vtCol = isset($vtColors[$vt->visit_type]) ? $vtColors[$vt->visit_type] : '#607d8b';
                                    $vtPct = round(((int)$vt->cnt / $vtMax) * 100);
                            ?>
                            <div class="rh-bar-wrap">
                                <div class="rh-bar-label"><?php echo htmlspecialchars($vtLbl); ?></div>
                                <div class="rh-bar-track">
                                    <div class="rh-bar-fill" style="width:<?php echo $vtPct; ?>%;background:<?php echo $vtCol; ?>;">
                                        <span class="rh-bar-val"><?php echo (int)$vt->cnt; ?></span>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; else: ?>
                            <p class="text-muted" style="font-size:12px;">No visit type data for this period.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── ROW 3: TOP DIAGNOSES + TOP MEDS ────────────────── -->
            <div class="row">
                <!-- Top Diagnoses -->
                <div class="col-md-6">
                    <div class="rh-box">
                        <div class="rh-box-header">
                            <i class="fa fa-stethoscope text-teal"></i>
                            Top 10 Diagnoses
                            <span class="ghs-badge">GHS Morbidity</span>
                            <span class="nhis-badge">ICD-10</span>
                        </div>
                        <div class="rh-box-body">
                            <?php if (!empty($stats['top_diagnoses'])):
                                $dMax = max(array_map(function($d){ return (int)$d->freq; }, $stats['top_diagnoses'])) ?: 1;
                                foreach ($stats['top_diagnoses'] as $i => $diag):
                                    $dpct = round(((int)$diag->freq / $dMax) * 100);
                            ?>
                            <div class="rh-bar-wrap">
                                <div class="rh-bar-label">
                                    <strong style="font-size:11px;color:#888;"><?php echo $i+1; ?>.</strong>
                                    <?php echo htmlspecialchars($diag->diagnosis_name); ?>
                                    <?php if ($diag->icd_code): ?>
                                    <span style="font-size:10px;color:#90a4ae;margin-left:4px;"><?php echo htmlspecialchars($diag->icd_code); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="rh-bar-track">
                                    <div class="rh-bar-fill" style="width:<?php echo $dpct; ?>%;background:#00897b;">
                                        <span class="rh-bar-val"><?php echo (int)$diag->freq; ?></span>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; else: ?>
                            <p class="text-muted" style="font-size:12px;text-align:center;padding:20px 0;">
                                <i class="fa fa-info-circle"></i> No diagnosis data for this period.
                            </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Top Medications -->
                <div class="col-md-6">
                    <div class="rh-box">
                        <div class="rh-box-header">
                            <i class="fa fa-medkit text-orange"></i>
                            Top 8 Prescribed Medications
                            <span class="nhis-badge">NHIS</span>
                        </div>
                        <div class="rh-box-body">
                            <?php if (!empty($stats['top_meds'])):
                                $mMax = max(array_map(function($m){ return (int)$m->freq; }, $stats['top_meds'])) ?: 1;
                                foreach ($stats['top_meds'] as $j => $med):
                                    $mpct = round(((int)$med->freq / $mMax) * 100);
                            ?>
                            <div class="rh-bar-wrap">
                                <div class="rh-bar-label">
                                    <strong style="font-size:11px;color:#888;"><?php echo $j+1; ?>.</strong>
                                    <?php echo htmlspecialchars($med->drug_name); ?>
                                </div>
                                <div class="rh-bar-track">
                                    <div class="rh-bar-fill" style="width:<?php echo $mpct; ?>%;background:#e65100;">
                                        <span class="rh-bar-val"><?php echo (int)$med->freq; ?></span>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; else: ?>
                            <p class="text-muted" style="font-size:12px;text-align:center;padding:20px 0;">
                                <i class="fa fa-info-circle"></i> No prescription data for this period.
                            </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── ROW 4: GENDER / AGE BREAKDOWN + COMPLIANCE NOTE ── -->
            <div class="row">
                <div class="col-md-5">
                    <div class="rh-box">
                        <div class="rh-box-header">
                            <i class="fa fa-bar-chart text-navy"></i>
                            Gender &amp; Age Breakdown
                            <span class="ghs-badge">GHS Form 1</span>
                        </div>
                        <div class="rh-box-body">
                            <?php
                            $male   = (int)$att->male_count;
                            $female = (int)$att->female_count;
                            $u5     = (int)$att->under5;
                            $o5     = (int)$att->over5;
                            $gTotal = ($male + $female) ?: 1;
                            $mPct   = round(($male   / $gTotal) * 100);
                            $fPct   = round(($female / $gTotal) * 100);
                            $aTotal = ($u5 + $o5) ?: 1;
                            $u5Pct  = round(($u5 / $aTotal) * 100);
                            $o5Pct  = round(($o5 / $aTotal) * 100);
                            ?>
                            <p style="font-size:12px;font-weight:600;margin-bottom:6px;">By Gender</p>
                            <div class="rh-bar-wrap">
                                <div class="rh-bar-label">Male</div>
                                <div class="rh-bar-track">
                                    <div class="rh-bar-fill" style="width:<?php echo $mPct; ?>%;background:#1565c0;">
                                        <span class="rh-bar-val"><?php echo $male; ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="rh-bar-wrap">
                                <div class="rh-bar-label">Female</div>
                                <div class="rh-bar-track">
                                    <div class="rh-bar-fill" style="width:<?php echo $fPct; ?>%;background:#c2185b;">
                                        <span class="rh-bar-val"><?php echo $female; ?></span>
                                    </div>
                                </div>
                            </div>
                            <hr style="margin:10px 0;">
                            <p style="font-size:12px;font-weight:600;margin-bottom:6px;">By Age Group</p>
                            <div class="rh-bar-wrap">
                                <div class="rh-bar-label">Under 5 yrs</div>
                                <div class="rh-bar-track">
                                    <div class="rh-bar-fill" style="width:<?php echo $u5Pct; ?>%;background:#f57f17;">
                                        <span class="rh-bar-val"><?php echo $u5; ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="rh-bar-wrap">
                                <div class="rh-bar-label">5 yrs and above</div>
                                <div class="rh-bar-track">
                                    <div class="rh-bar-fill" style="width:<?php echo $o5Pct; ?>%;background:#00897b;">
                                        <span class="rh-bar-val"><?php echo $o5; ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-7">
                    <div class="rh-box">
                        <div class="rh-box-header">
                            <i class="fa fa-info-circle text-blue"></i>
                            GHS &amp; NHIS Compliance Summary
                        </div>
                        <div class="rh-box-body">
                            <table class="table table-condensed table-striped" style="font-size:13px;">
                                <thead>
                                    <tr>
                                        <th>Indicator</th>
                                        <th style="text-align:right;">Value</th>
                                        <th>Standard</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><i class="fa fa-users text-teal"></i> Total OPD Attendance</td>
                                        <td style="text-align:right;font-weight:700;"><?php echo (int)$att->total_visits; ?></td>
                                        <td><span class="ghs-badge">GHS Form 1</span></td>
                                    </tr>
                                    <tr>
                                        <td><i class="fa fa-child text-orange"></i> Under-5 Attendance</td>
                                        <td style="text-align:right;font-weight:700;"><?php echo (int)$att->under5; ?></td>
                                        <td><span class="ghs-badge">DHIMS2</span></td>
                                    </tr>
                                    <tr>
                                        <td><i class="fa fa-shield text-blue"></i> NHIS Beneficiaries</td>
                                        <td style="text-align:right;font-weight:700;"><?php echo (int)$pay->nhis_count; ?></td>
                                        <td><span class="nhis-badge">NHIS</span></td>
                                    </tr>
                                    <tr>
                                        <td><i class="fa fa-stethoscope text-green"></i> Completed Consultations</td>
                                        <td style="text-align:right;font-weight:700;"><?php echo (int)$att->completed; ?></td>
                                        <td><span class="ghs-badge">GHS</span></td>
                                    </tr>
                                    <tr>
                                        <td><i class="fa fa-flask text-purple"></i> Lab Investigations</td>
                                        <td style="text-align:right;font-weight:700;"><?php echo (int)$lab->total_labs; ?></td>
                                        <td><span class="ghs-badge">GHS</span></td>
                                    </tr>
                                    <tr>
                                        <td><i class="fa fa-medkit text-red"></i> Prescriptions</td>
                                        <td style="text-align:right;font-weight:700;"><?php echo (int)$rx->total_rx; ?></td>
                                        <td><span class="nhis-badge">NHIS</span></td>
                                    </tr>
                                    <tr>
                                        <td><i class="fa fa-bed text-olive"></i> Admitted (OPD→IPD)</td>
                                        <td style="text-align:right;font-weight:700;"><?php echo (int)$adm->admitted_count; ?></td>
                                        <td><span class="ghs-badge">GHS</span></td>
                                    </tr>
                                    <tr>
                                        <td><i class="fa fa-clock-o text-slate"></i> Avg Consult Time (min)</td>
                                        <td style="text-align:right;font-weight:700;"><?php echo $avg > 0 ? number_format($avg,1) : '—'; ?></td>
                                        <td><span class="ghs-badge">GHS QA</span></td>
                                    </tr>
                                </tbody>
                            </table>
                            <p class="text-muted" style="font-size:11px;margin-top:6px;">
                                <i class="fa fa-lock"></i>
                                Data sourced from: <code>iop_opd_workflow</code>, <code>iop_diagnosis</code>,
                                <code>iop_medication</code>, <code>iop_laboratory</code>, <code>patient_details_iop</code>.
                                Period: <strong><?php echo date('d M Y', strtotime($date_from)); ?></strong>
                                to <strong><?php echo date('d M Y', strtotime($date_to)); ?></strong>.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

        </section><!-- /.content -->
    </aside>
</div>

<script src="<?php echo base_url() ?>public/js/jquery.min.js"></script>
<script src="<?php echo base_url() ?>public/js/bootstrap.min.js" type="text/javascript"></script>
<script src="<?php echo base_url() ?>public/js/AdminLTE/app.js" type="text/javascript"></script>
<script src="<?php echo base_url() ?>public/datepicker/js/bootstrap-datepicker.js"></script>
<script>
$(function() {
    $('#rh_from, #rh_to').datepicker({ format: 'yyyy-mm-dd', autoclose: true, todayHighlight: true });
});
</script>
</body>
</html>
