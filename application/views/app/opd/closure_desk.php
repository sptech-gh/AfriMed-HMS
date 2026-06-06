<?php if (!defined('BASEPATH')) exit('No direct script access allowed'); ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>OPD Closure Desk</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">

    <link href="<?php echo base_url() ?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />

    <style>
        .box { box-shadow: 0 1px 3px rgba(0,0,0,0.08); border: none; }
        .box-header { padding: 12px 15px; background: #fff; border-bottom: 1px solid #eee; }
        .box-header .box-title { font-size: 14px; font-weight: 600; color: #333; }
        .table > thead > tr > th { background: #f5f6f8; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.4px; color: #555; padding: 10px 12px; border-bottom: 2px solid #ddd; white-space: nowrap; }
        .table > tbody > tr > td { padding: 10px 12px; vertical-align: middle; font-size: 13px; }
        .label { font-size: 11px; font-weight: 600; padding: 4px 8px; border-radius: 3px; }
        .btn-xs { font-size: 11px; padding: 3px 8px; border-radius: 3px; }
        .muted { color: #999; }
        .kpi { font-size: 12px; }
        .kpi strong { font-size: 13px; }
        .action-cell { white-space: nowrap; }
    </style>
</head>

<body class="skin-blue">
<?php require_once(APPPATH . 'views/include/header.php'); ?>

<div class="wrapper row-offcanvas row-offcanvas-left">

    <?php require_once(APPPATH . 'views/include/sidebar.php'); ?>

    <aside class="right-side">
        <section class="content-header">
            <h1>OPD Closure Desk <small class="muted">Close stale OPD visits older than <?php echo (int)$hours; ?> hours</small></h1>
            <ol class="breadcrumb">
                <li><a href="<?php echo base_url() ?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                <li><a href="<?php echo base_url() ?>app/opd">OPD</a></li>
                <li class="active">Closure Desk</li>
            </ol>
        </section>

        <section class="content">

            <?php echo isset($message) ? $message : ''; ?>

            <div class="row">
                <div class="col-md-12">
                    <div class="box">
                        <div class="box-header">
                            <i class="fa fa-lock"></i>
                            <h3 class="box-title">Stale OPD Visits</h3>
                            <div class="box-tools pull-right">
                                <a href="<?php echo base_url(); ?>app/opd" class="btn btn-default btn-xs"><i class="fa fa-arrow-left"></i> Back to OPD</a>
                            </div>
                        </div>
                        <div class="box-body table-responsive no-padding">

                            <table class="table table-hover" style="margin-bottom:0;">
                                <thead>
                                <tr>
                                    <th>OPD No.</th>
                                    <th>Patient</th>
                                    <th>Doctor</th>
                                    <th>Visit Time</th>
                                    <th>Status</th>
                                    <th class="text-center">Pending Lab</th>
                                    <th class="text-center">Pending Rx</th>
                                    <th class="text-right">Billing Balance</th>
                                    <th>Eligible</th>
                                    <th>Recommended</th>
                                    <th></th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php if (!isset($stale_visits) || !is_array($stale_visits) || count($stale_visits) === 0): ?>
                                    <tr>
                                        <td colspan="11" class="text-center muted" style="padding:20px;">
                                            <i class="fa fa-check-circle"></i> No stale visits found.
                                        </td>
                                    </tr>
                                <?php else: foreach ($stale_visits as $v): ?>
                                    <?php
                                        $iop_safe = str_replace(' ', '-', (string)$v['iop_id']);
                                        $pno_safe = str_replace(' ', '-', (string)$v['patient_no']);
                                        $canClose = !empty($v['can_close']);
                                        $rec = isset($v['recommended_action']) ? (string)$v['recommended_action'] : '';
                                        $eligibleBadge = $canClose ? '<span class="label label-success"><i class="fa fa-check"></i> Yes</span>' : '<span class="label label-default">No</span>';
                                    ?>
                                    <tr id="row-<?php echo htmlspecialchars($iop_safe); ?>">
                                        <td>
                                            <a href="<?php echo base_url(); ?>app/opd/view/<?php echo htmlspecialchars($iop_safe); ?>/<?php echo htmlspecialchars($pno_safe); ?>">
                                                <?php echo htmlspecialchars((string)$v['iop_id']); ?>
                                            </a>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars((string)$v['patient_name']); ?></strong>
                                            <br><small class="muted"><?php echo htmlspecialchars((string)$v['patient_no']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars((string)$v['doctor_name']); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars((string)$v['visit_datetime']); ?>
                                            <br><small class="muted"><?php echo htmlspecialchars((string)$v['age_hint']); ?></small>
                                        </td>
                                        <td><?php echo (string)$v['status_badge']; ?></td>
                                        <td class="text-center"><?php echo (int)$v['pending_lab_count']; ?></td>
                                        <td class="text-center"><?php echo (int)$v['pending_rx_count']; ?></td>
                                        <td class="text-right"><?php echo number_format((float)$v['billing_balance'], 2); ?></td>
                                        <td><?php echo $eligibleBadge; ?></td>
                                        <td>
                                            <?php if ($rec === 'CANCEL'): ?>
                                                <span class="label label-default"><i class="fa fa-times"></i> Cancel (No Show)</span>
                                            <?php elseif ($rec === 'CLINICAL_CLEAR'): ?>
                                                <span class="label label-success"><i class="fa fa-check-circle"></i> Close (Clinically Clear)</span>
                                            <?php else: ?>
                                                <span class="muted">—</span>
                                            <?php endif; ?>
                                            <?php if (!empty($v['blocker_text'])): ?>
                                                <br><small class="muted"><?php echo htmlspecialchars((string)$v['blocker_text']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="action-cell">
                                            <?php if ($canClose && $rec === 'CANCEL'): ?>
                                                <button type="button" class="btn btn-xs btn-default close-visit-btn" data-iop="<?php echo htmlspecialchars((string)$v['iop_id']); ?>" data-mode="cancel">
                                                    <i class="fa fa-times"></i> Cancel
                                                </button>
                                            <?php elseif ($canClose && $rec === 'CLINICAL_CLEAR'): ?>
                                                <button type="button" class="btn btn-xs btn-success close-visit-btn" data-iop="<?php echo htmlspecialchars((string)$v['iop_id']); ?>" data-mode="clinical_clear">
                                                    <i class="fa fa-check"></i> Close
                                                </button>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-xs btn-default" disabled="disabled" style="opacity:0.6;cursor:not-allowed;">
                                                    <i class="fa fa-lock"></i> Blocked
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; endif; ?>
                                </tbody>
                            </table>

                        </div>
                    </div>
                </div>
            </div>

        </section>
    </aside>
</div>

<script src="<?php echo base_url(); ?>public/js/jquery.min.js"></script>
<script src="<?php echo base_url(); ?>public/js/bootstrap.min.js" type="text/javascript"></script>
<script src="<?php echo base_url(); ?>public/js/AdminLTE/app.js" type="text/javascript"></script>

<script>
    $(document).on('click', '.close-visit-btn', function() {
        var $btn = $(this);
        var iop_id = $btn.data('iop');
        var mode = $btn.data('mode');

        var msg = (mode === 'cancel')
            ? 'Cancel this stale OPD visit?'
            : 'Close this stale OPD visit (Clinically Clear)? This will lock the visit.';

        if (!confirm(msg)) {
            return;
        }

        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');

        $.ajax({
            url: '<?php echo base_url(); ?>app/opd/close_stale_visit_ajax',
            type: 'POST',
            dataType: 'json',
            data: {
                iop_id: iop_id,
                mode: mode,
                hours: <?php echo (int)$hours; ?>,
                <?php echo $this->security->get_csrf_token_name(); ?>: '<?php echo $this->security->get_csrf_hash(); ?>'
            },
            success: function(resp) {
                if (resp && resp.ok) {
                    var rowId = 'row-' + String(iop_id).replace(/\s/g, '-');
                    $('#' + rowId).fadeOut(200, function(){ $(this).remove(); });
                    return;
                }
                alert((resp && resp.error) ? resp.error : 'Failed');
                $btn.prop('disabled', false).html((mode === 'cancel') ? '<i class="fa fa-times"></i> Cancel' : '<i class="fa fa-check"></i> Close');
            },
            error: function() {
                alert('Request failed');
                $btn.prop('disabled', false).html((mode === 'cancel') ? '<i class="fa fa-times"></i> Cancel' : '<i class="fa fa-check"></i> Close');
            }
        });
    });
</script>

</body>
</html>
