<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Controlled Substances Management</title>
    <meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>
    <link href="<?php echo base_url(); ?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
    <style>
        :root {
            --danger:  #dd4b39;
            --warning: #f39c12;
            --success: #00a65a;
            --primary: #3c8dbc;
            --purple:  #605ca8;
            --shadow:  0 2px 10px rgba(0,0,0,0.09);
            --radius:  8px;
        }
        /* Stat cards */
        .cd-stat { background:#fff; border-radius:var(--radius); box-shadow:var(--shadow);
            padding:16px 18px; display:flex; align-items:center; gap:14px;
            border-left:4px solid var(--danger); margin-bottom:16px; text-decoration:none; color:inherit; transition:box-shadow .2s; }
        .cd-stat:hover { box-shadow:0 4px 18px rgba(0,0,0,.13); text-decoration:none; color:inherit; }
        .cd-stat .cd-num { font-size:28px; font-weight:700; line-height:1; }
        .cd-stat .cd-lbl { font-size:11px; color:#888; text-transform:uppercase; letter-spacing:.4px; margin-top:3px; }
        .cd-stat .cd-icon { font-size:32px; opacity:.15; margin-left:auto; }
        /* Filter bar */
        .cd-filter { background:#fff; border-radius:var(--radius); box-shadow:var(--shadow);
            padding:14px 18px; margin-bottom:18px; display:flex; gap:10px; flex-wrap:wrap; align-items:center; }
        .cd-filter input, .cd-filter select { height:34px; border:1.5px solid #ddd; border-radius:6px;
            padding:4px 10px; font-size:13px; color:#333; }
        .cd-filter input:focus, .cd-filter select:focus { outline:none; border-color:var(--primary); }
        .cd-filter-btn { padding:6px 16px; border-radius:6px; border:none; font-size:13px;
            font-weight:600; cursor:pointer; transition:opacity .2s; }
        .cd-filter-btn.primary { background:var(--primary); color:#fff; }
        .cd-filter-btn.default { background:#e9ecef; color:#555; }
        .cd-filter-btn:hover { opacity:.85; }
        /* Table */
        .cd-table-wrap { background:#fff; border-radius:var(--radius); box-shadow:var(--shadow); overflow:hidden; margin-bottom:18px; }
        .cd-table-header { padding:14px 18px; border-bottom:1px solid #f0f0f0;
            display:flex; justify-content:space-between; align-items:center; }
        .cd-table-header h4 { margin:0; font-weight:700; font-size:15px; }
        .cd-table { width:100%; border-collapse:collapse; font-size:13px; }
        .cd-table thead th { background:#f5f7fa; padding:10px 12px; font-weight:700;
            text-align:left; border-bottom:2px solid #e0e0e0; white-space:nowrap; }
        .cd-table tbody tr { border-bottom:1px solid #f2f2f2; transition:background .15s; }
        .cd-table tbody tr:hover { background:#f7fbff; }
        .cd-table td { padding:10px 12px; vertical-align:middle; }
        /* Schedule badges */
        .sched { display:inline-block; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:700; }
        .sched.I, .sched.II       { background:#f8d7da; color:#721c24; }
        .sched.III                { background:#fff3cd; color:#856404; }
        .sched.IV                 { background:#d1ecf1; color:#0c5460; }
        .sched.V                  { background:#e9ecef; color:#495057; }
        .sched.OTHER              { background:#e9ecef; color:#495057; }
        /* Req pills */
        .req-yes { display:inline-block; padding:2px 9px; border-radius:10px; font-size:11px;
            font-weight:700; background:#f8d7da; color:#721c24; }
        .req-no  { display:inline-block; padding:2px 9px; border-radius:10px; font-size:11px;
            font-weight:700; background:#e9ecef; color:#666; }
        /* Legend */
        .cd-legend { background:#fff; border-radius:var(--radius); box-shadow:var(--shadow);
            overflow:hidden; margin-bottom:18px; }
        .cd-legend-hdr { padding:12px 18px; border-bottom:1px solid #f0f0f0; cursor:pointer;
            display:flex; justify-content:space-between; align-items:center; user-select:none; }
        .cd-legend-hdr h4 { margin:0; font-size:14px; font-weight:700; }
        .cd-legend-body { padding:18px; display:none; }
        /* Nav */
        .cd-nav { display:flex; gap:10px; flex-wrap:wrap; margin-top:4px; }
        .cd-nav a { flex:1; min-width:160px; }
        /* Search highlight */
        #cdSearch { width:220px; }
    </style>
</head>
<body class="skin-blue">
<?php require_once(APPPATH.'views/include/header.php');?>
<div class="wrapper row-offcanvas row-offcanvas-left">
    <?php require_once(APPPATH.'views/include/sidebar.php');?>
    <aside class="right-side">
        <section class="content-header">
            <h1><i class="fa fa-shield"></i> Controlled Substances Management</h1>
            <ol class="breadcrumb">
                <li><a href="<?php echo base_url(); ?>app/pharmacy"><i class="fa fa-medkit"></i> Pharmacy</a></li>
                <li class="active">Controlled Drugs</li>
            </ol>
        </section>

        <section class="content">
            <?php echo isset($message) ? $message : ''; ?>

            <?php
                $drugs            = isset($drugs)            && is_array($drugs)            ? $drugs            : array();
                $schedules        = isset($schedules)        && is_array($schedules)        ? $schedules        : array();
                $filters          = isset($filters)          ? $filters          : array('schedule_id' => '', 'search' => '');
                $pending_auth_count = isset($pending_auth_count) ? (int)$pending_auth_count : 0;
                $is_admin         = ($this->session->userdata('role') === 'admin');
                $scheduleColour   = function($code) {
                    $n = (int)preg_replace('/[^0-9]/', '', $code);
                    if ($n <= 2)      return 'I';
                    elseif ($n === 3) return 'III';
                    elseif ($n === 4) return 'IV';
                    elseif ($n === 5) return 'V';
                    return 'OTHER';
                };
            ?>

            <!-- Stat cards -->
            <div class="row">
                <div class="col-md-3 col-sm-6">
                    <div class="cd-stat" style="border-left-color:var(--danger);">
                        <div>
                            <div class="cd-num text-danger"><?php echo count($drugs); ?></div>
                            <div class="cd-lbl">Controlled Drugs</div>
                        </div>
                        <i class="fa fa-shield cd-icon text-danger"></i>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <a href="<?php echo base_url(); ?>app/pharmacy/pending_authorizations" class="cd-stat" style="border-left-color:var(--warning);">
                        <div>
                            <div class="cd-num text-warning"><?php echo $pending_auth_count; ?></div>
                            <div class="cd-lbl">Pending Authorizations</div>
                        </div>
                        <i class="fa fa-clock-o cd-icon text-warning"></i>
                    </a>
                </div>
                <div class="col-md-3 col-sm-6">
                    <a href="<?php echo base_url(); ?>app/pharmacy/controlled_register" class="cd-stat" style="border-left-color:var(--primary);">
                        <div>
                            <div class="cd-num text-primary"><i class="fa fa-book" style="font-size:22px;"></i></div>
                            <div class="cd-lbl">Drug Register</div>
                        </div>
                        <i class="fa fa-book cd-icon text-primary"></i>
                    </a>
                </div>
                <?php if ($is_admin): ?>
                <div class="col-md-3 col-sm-6">
                    <a href="<?php echo base_url(); ?>app/pharmacy/controlled_drug_schedules" class="cd-stat" style="border-left-color:var(--purple);">
                        <div>
                            <div class="cd-num text-purple"><?php echo count($schedules); ?></div>
                            <div class="cd-lbl">Drug Schedules</div>
                        </div>
                        <i class="fa fa-list-ol cd-icon text-purple"></i>
                    </a>
                </div>
                <?php endif; ?>
            </div>

            <!-- Filter bar -->
            <form method="GET" action="">
                <div class="cd-filter">
                    <select name="schedule_id">
                        <option value="">All Schedules</option>
                        <?php foreach ($schedules as $s): ?>
                        <option value="<?php echo (int)$s->schedule_id; ?>" <?php echo (isset($filters['schedule_id']) && $filters['schedule_id'] == $s->schedule_id) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($s->schedule_code); ?> — <?php echo htmlspecialchars($s->schedule_name); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="search" id="cdSearch" placeholder="Search drug name…" value="<?php echo htmlspecialchars($filters['search'] ?? ''); ?>">
                    <button type="submit" class="cd-filter-btn primary"><i class="fa fa-search"></i> Filter</button>
                    <a href="<?php echo base_url(); ?>app/pharmacy/controlled_drugs" class="cd-filter-btn default"><i class="fa fa-refresh"></i> Clear</a>
                </div>
            </form>

            <!-- Controlled Drugs Table -->
            <div class="cd-table-wrap">
                <div class="cd-table-header">
                    <h4><i class="fa fa-shield text-danger"></i> Controlled Substances
                        <span style="background:#f8d7da;color:#721c24;border-radius:12px;padding:2px 9px;font-size:12px;margin-left:6px;"><?php echo count($drugs); ?></span>
                    </h4>
                </div>
                <div style="overflow-x:auto;">
                    <table class="cd-table" id="controlledTable">
                        <thead><tr>
                            <th>Drug Name</th>
                            <th>Schedule</th>
                            <th>Double Auth</th>
                            <th>Witness</th>
                            <th>Max Days</th>
                            <th>Notes</th>
                            <th style="width:80px;">Actions</th>
                        </tr></thead>
                        <tbody>
                        <?php if (count($drugs) === 0): ?>
                            <tr><td colspan="7" style="text-align:center;padding:36px;color:#aaa;">
                                <i class="fa fa-inbox" style="font-size:32px;display:block;margin-bottom:8px;"></i>
                                No controlled drugs found.
                            </td></tr>
                        <?php else: ?>
                        <?php foreach ($drugs as $drug):
                            $sc   = isset($drug->schedule_code) ? (string)$drug->schedule_code : '';
                            $scCls = $scheduleColour($sc);
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($drug->drug_name); ?></strong>
                                <?php if (!empty($drug->generic_name)): ?>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($drug->generic_name); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="sched <?php echo $scCls; ?>"><?php echo htmlspecialchars($sc); ?></span>
                                <br><small class="text-muted"><?php echo htmlspecialchars($drug->schedule_name ?? ''); ?></small>
                            </td>
                            <td style="text-align:center;">
                                <?php if (!empty($drug->requires_double_auth)): ?>
                                    <span class="req-yes"><i class="fa fa-users"></i> Required</span>
                                <?php else: ?>
                                    <span class="req-no">No</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center;">
                                <?php if (!empty($drug->requires_witness)): ?>
                                    <span class="req-yes"><i class="fa fa-eye"></i> Required</span>
                                <?php else: ?>
                                    <span class="req-no">No</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center;">
                                <?php echo isset($drug->max_days_supply) && $drug->max_days_supply ? (int)$drug->max_days_supply . 'd' : '—'; ?>
                            </td>
                            <td><small class="text-muted"><?php echo htmlspecialchars($drug->controlled_notes ?? '—'); ?></small></td>
                            <td>
                                <button type="button" class="btn btn-xs btn-warning btn-edit-controlled"
                                        data-drug-id="<?php echo (int)$drug->drug_id; ?>"
                                        data-drug-name="<?php echo htmlspecialchars($drug->drug_name); ?>"
                                        data-schedule-id="<?php echo (int)($drug->schedule_id ?? 0); ?>"
                                        data-notes="<?php echo htmlspecialchars($drug->controlled_notes ?? ''); ?>">
                                    <i class="fa fa-edit"></i> Edit
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Schedule Legend (collapsible) -->
            <div class="cd-legend">
                <div class="cd-legend-hdr" onclick="cdToggleLegend()">
                    <h4><i class="fa fa-info-circle"></i> Schedule Legend</h4>
                    <i class="fa fa-chevron-down" id="cdLegendChevron"></i>
                </div>
                <div class="cd-legend-body" id="cdLegendBody">
                    <?php
                        $scheduleClassMap = array(
                            'SCHEDULE_I' => 'I', 'SCHEDULE_II' => 'I',
                            'SCHEDULE_III' => 'III', 'SCHEDULE_IV' => 'IV', 'SCHEDULE_V' => 'V',
                        );
                    ?>
                    <div style="overflow-x:auto;">
                        <table class="cd-table">
                            <thead><tr>
                                <th>Schedule</th>
                                <th>Name</th>
                                <th>Description</th>
                                <th>Requirements</th>
                            </tr></thead>
                            <tbody>
                            <?php foreach ($schedules as $s):
                                $sCls = $scheduleColour((string)$s->schedule_code);
                            ?>
                            <tr>
                                <td><span class="sched <?php echo $sCls; ?>"><?php echo htmlspecialchars($s->schedule_code); ?></span></td>
                                <td><strong><?php echo htmlspecialchars($s->schedule_name); ?></strong></td>
                                <td><small><?php echo htmlspecialchars($s->description ?? ''); ?></small></td>
                                <td>
                                    <?php if (!empty($s->requires_double_auth)): ?>
                                        <span class="req-yes" style="margin-right:4px;"><i class="fa fa-users"></i> Double Auth</span>
                                    <?php endif; ?>
                                    <?php if (!empty($s->requires_witness)): ?>
                                        <span class="req-yes"><i class="fa fa-eye"></i> Witness</span>
                                    <?php endif; ?>
                                    <?php if (empty($s->requires_double_auth) && empty($s->requires_witness)): ?>
                                        <span class="req-no">Standard</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Navigation -->
            <div class="cd-nav">
                <a href="<?php echo base_url(); ?>app/pharmacy/pending_authorizations" class="btn btn-warning">
                    <i class="fa fa-clock-o"></i> Pending Authorizations
                    <?php if ($pending_auth_count > 0): ?>
                        <span class="badge" style="background:#fff;color:var(--warning);margin-left:4px;"><?php echo $pending_auth_count; ?></span>
                    <?php endif; ?>
                </a>
                <a href="<?php echo base_url(); ?>app/pharmacy/controlled_register" class="btn btn-info">
                    <i class="fa fa-book"></i> View Register
                </a>
                <a href="<?php echo base_url(); ?>app/pharmacy" class="btn btn-default">
                    <i class="fa fa-arrow-left"></i> Back to Worklist
                </a>
            </div>

        </section>
    </aside>
</div>

<!-- Edit Controlled Drug Modal -->
<div class="modal fade" id="editControlledModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" id="editControlledForm">
            <div class="modal-content">
                <div class="modal-header" style="background:var(--warning);color:#fff;">
                    <button type="button" class="close" data-dismiss="modal" style="color:#fff;">&times;</button>
                    <h4 class="modal-title"><i class="fa fa-shield"></i> Edit Controlled Drug Status</h4>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label style="font-size:12px;color:#888;text-transform:uppercase;">Drug Name</label>
                        <input type="text" id="edit_drug_name" class="form-control" readonly style="background:#f8f9fa;font-weight:700;">
                    </div>
                    <div class="form-group">
                        <label style="font-size:12px;color:#888;text-transform:uppercase;">Schedule <span class="text-danger">*</span></label>
                        <select name="schedule_id" id="edit_schedule_id" class="form-control" required>
                            <option value="">— Remove Controlled Status —</option>
                            <?php foreach ($schedules as $s): ?>
                            <option value="<?php echo (int)$s->schedule_id; ?>"><?php echo htmlspecialchars($s->schedule_code); ?> — <?php echo htmlspecialchars($s->schedule_name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label style="font-size:12px;color:#888;text-transform:uppercase;">Notes</label>
                        <textarea name="controlled_notes" id="edit_notes" class="form-control" rows="3" placeholder="Storage requirements, special handling instructions…"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning"><i class="fa fa-save"></i> Update</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script src="<?php echo base_url(); ?>public/js/jquery.min.js"></script>
<script src="<?php echo base_url(); ?>public/js/bootstrap.min.js"></script>
<script src="<?php echo base_url(); ?>public/js/AdminLTE/app.js"></script>
<script>
(function($) {
    'use strict';
    var BASE = '<?php echo base_url(); ?>';

    $(function() {
        $('.btn-edit-controlled').on('click', function() {
            var $b = $(this);
            $('#editControlledForm').attr('action', BASE + 'app/pharmacy/set_controlled/' + $b.data('drug-id'));
            $('#edit_drug_name').val($b.data('drug-name'));
            $('#edit_schedule_id').val($b.data('schedule-id'));
            $('#edit_notes').val($b.data('notes') || '');
            $('#editControlledModal').modal('show');
        });
    });

    window.cdToggleLegend = function() {
        var $b = $('#cdLegendBody');
        var $i = $('#cdLegendChevron');
        if ($b.is(':visible')) {
            $b.slideUp(200);
            $i.removeClass('fa-chevron-up').addClass('fa-chevron-down');
        } else {
            $b.slideDown(200);
            $i.removeClass('fa-chevron-down').addClass('fa-chevron-up');
        }
    };
})(jQuery);
</script>
</body>
</html>
