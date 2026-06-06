<!DOCTYPE html>
<html>
<head>
        <meta charset="UTF-8">
        <title>Pending Stock Approvals</title>
        <meta content="width=device-width, initial-scale=1.0" name="viewport">
        <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
        <link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
        <link href="<?php echo base_url();?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
        <link href="<?php echo base_url();?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
        <link href="<?php echo base_url();?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
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
        .pa-stat { background:#fff; border-radius:var(--radius); box-shadow:var(--shadow);
            padding:18px 20px; display:flex; align-items:center; gap:16px;
            border-left:4px solid var(--warning); margin-bottom:18px; }
        .pa-stat .pa-num { font-size:34px; font-weight:700; color:var(--warning); line-height:1; }
        .pa-stat .pa-lbl { font-size:12px; color:#777; text-transform:uppercase; letter-spacing:.5px; margin-top:3px; }
        .pa-stat .pa-icon { font-size:40px; opacity:.15; margin-left:auto; color:var(--warning); }
        /* Tab nav */
        .pa-tabs { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:16px; }
        .pa-tab { padding:8px 20px; border-radius:20px; border:2px solid #ddd;
            background:#f5f5f5; font-size:13px; font-weight:600; cursor:pointer; color:#555; transition:all .2s; }
        .pa-tab:hover { background:#e0e0e0; }
        .pa-tab.active { background:var(--primary); border-color:var(--primary); color:#fff; }
        .pa-panel { display:none; }
        .pa-panel.active { display:block; }
        /* Table */
        .pa-table-wrap { background:#fff; border-radius:var(--radius); box-shadow:var(--shadow); overflow:hidden; }
        .pa-table-header { padding:14px 18px; border-bottom:1px solid #f0f0f0;
            display:flex; justify-content:space-between; align-items:center; }
        .pa-table-header h4 { margin:0; font-weight:700; font-size:15px; }
        .pa-table { width:100%; border-collapse:collapse; font-size:13px; }
        .pa-table thead th { background:#f5f7fa; padding:10px 12px; font-weight:700;
            text-align:left; border-bottom:2px solid #e0e0e0; white-space:nowrap; }
        .pa-table tbody tr { border-bottom:1px solid #f2f2f2; transition:background .15s; }
        .pa-table tbody tr:hover { background:#f7fbff; }
        .pa-table td { padding:10px 12px; vertical-align:middle; }
        /* Type badges */
        .pa-type { display:inline-block; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:700; }
        .pa-type.restock      { background:#d1ecf1; color:#0c5460; }
        .pa-type.batch_restock{ background:#cce5ff; color:#004085; }
        .pa-type.adjustment   { background:#fff3cd; color:#856404; }
        .pa-type.add          { background:#d4edda; color:#155724; }
        .pa-type.other        { background:#e9ecef; color:#495057; }
        /* Status badges */
        .pa-status { display:inline-block; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:700; }
        .pa-status.pending  { background:#fff3cd; color:#856404; }
        .pa-status.approved { background:#d4edda; color:#155724; }
        .pa-status.rejected { background:#f8d7da; color:#721c24; }
        /* Action buttons */
        .pa-approve-btn { background:var(--success); color:#fff; border:none; padding:5px 12px;
            border-radius:6px; font-size:12px; font-weight:600; cursor:pointer; transition:opacity .2s; }
        .pa-approve-btn:hover { opacity:.85; }
        .pa-reject-btn  { background:var(--danger);  color:#fff; border:none; padding:5px 12px;
            border-radius:6px; font-size:12px; font-weight:600; cursor:pointer; transition:opacity .2s; margin-left:4px; }
        .pa-reject-btn:hover { opacity:.85; }
        /* Modal */
        .pa-modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:9000; display:none; align-items:center; justify-content:center; }
        .pa-modal-overlay.show { display:flex; }
        .pa-modal { background:#fff; border-radius:var(--radius); box-shadow:0 10px 40px rgba(0,0,0,.2);
            padding:28px; max-width:460px; width:92%; }
        .pa-modal h4 { margin:0 0 16px; font-weight:700; }
        .pa-modal textarea { width:100%; border:1.5px solid #ddd; border-radius:6px; padding:10px; font-size:13px; resize:vertical; min-height:80px; }
        .pa-modal textarea:focus { outline:none; border-color:var(--primary); }
        .pa-modal-footer { display:flex; gap:8px; margin-top:16px; justify-content:flex-end; }
        /* Toast */
        .pa-toast-wrap { position:fixed; top:70px; right:20px; z-index:9999; min-width:260px; }
        .pa-toast { padding:12px 18px; border-radius:8px; margin-bottom:8px;
            font-size:13px; font-weight:600; box-shadow:0 4px 12px rgba(0,0,0,.15);
            animation:paSlide .3s ease; color:#fff; }
        .pa-toast.success { background:var(--success); }
        .pa-toast.error   { background:var(--danger);  }
        .pa-toast.info    { background:var(--primary);  }
        @keyframes paSlide { from{transform:translateX(120%);opacity:0;} to{transform:translateX(0);opacity:1;} }
        .empty-box { text-align:center; padding:40px; color:#aaa; }
        .empty-box i { font-size:40px; display:block; margin-bottom:10px; }
        </style>
    </head>
    <body class="skin-blue">
        <?php require_once(APPPATH.'views/include/header.php');?>
        <div class="wrapper row-offcanvas row-offcanvas-left">
            <?php require_once(APPPATH.'views/include/sidebar.php');?>
            <aside class="right-side">
                <section class="content-header">
                    <h1><i class="fa fa-check-square-o"></i> Pending Stock Approvals
                        <small>Admin Approval Dashboard</small>
                    </h1>
                    <ol class="breadcrumb">
                        <li><a href="<?php echo base_url(); ?>app/dashboard"><i class="fa fa-dashboard"></i> Dashboard</a></li>
                        <li><a href="<?php echo base_url(); ?>app/pharmacy/stock">Pharmacy Stock</a></li>
                        <li class="active">Pending Approvals</li>
                    </ol>
                </section>

                <section class="content">
                <?php if (isset($message) && $message) { echo $message; } ?>

                <?php
                    $pending   = isset($pending)   && is_array($pending)   ? $pending   : array();
                    $history   = isset($history)   && is_array($history)   ? $history   : array();
                    $audit_log = isset($audit_log) && is_array($audit_log) ? $audit_log : array();
                    $pCount    = count($pending);
                ?>

                <!-- Stat card -->
                <div class="pa-stat">
                    <div>
                        <div class="pa-num" id="pendingCountNum"><?php echo $pCount; ?></div>
                        <div class="pa-lbl">Pending Stock Requests</div>
                    </div>
                    <i class="fa fa-clock-o pa-icon"></i>
                </div>

                <!-- Tab nav -->
                <div class="pa-tabs">
                    <button class="pa-tab active" onclick="paTab('pending')">
                        <i class="fa fa-clock-o"></i> Pending
                        <span id="pendingBadge" style="background:var(--warning);color:#fff;border-radius:10px;padding:1px 7px;margin-left:4px;font-size:11px;"><?php echo $pCount; ?></span>
                    </button>
                    <button class="pa-tab" onclick="paTab('history')">
                        <i class="fa fa-history"></i> Request History
                    </button>
                    <button class="pa-tab" onclick="paTab('audit')">
                        <i class="fa fa-shield"></i> Stock Audit Log
                    </button>
                </div>

                <!-- PENDING panel -->
                <div class="pa-panel active" id="panel-pending">
                    <div class="pa-table-wrap">
                        <div class="pa-table-header">
                            <h4><i class="fa fa-clock-o text-warning"></i> Pending Requests</h4>
                            <small class="text-muted">Approve or reject each request below</small>
                        </div>
                        <?php if ($pCount > 0): ?>
                        <div style="overflow-x:auto;">
                            <table class="pa-table" id="pendingTable">
                                <thead><tr>
                                    <th>#</th>
                                    <th>Medication</th>
                                    <th>Type</th>
                                    <th>Qty</th>
                                    <th>Batch</th>
                                    <th>Expiry</th>
                                    <th>Reason</th>
                                    <th>Requested By</th>
                                    <th>Date</th>
                                    <th style="width:140px;">Actions</th>
                                </tr></thead>
                                <tbody id="pendingTbody">
                                <?php foreach ($pending as $r):
                                    $reqName = trim((string)$r->requester_first . ' ' . (string)$r->requester_last);
                                    if ($reqName === '') $reqName = (string)$r->requester_name;
                                    $rtype = strtolower((string)$r->request_type);
                                    $knownTypes = ['restock','batch_restock','adjustment','add'];
                                    $typeCls = in_array($rtype, $knownTypes) ? $rtype : 'other';
                                ?>
                                <tr id="pendingRow<?php echo (int)$r->id; ?>">
                                    <td><small class="text-muted">#<?php echo (int)$r->id; ?></small></td>
                                    <td><strong><?php echo htmlspecialchars($r->drug_name); ?></strong></td>
                                    <td><span class="pa-type <?php echo $typeCls; ?>"><?php echo strtoupper($r->request_type); ?></span></td>
                                    <td><strong><?php echo number_format((float)$r->quantity, 0); ?></strong></td>
                                    <td><code><?php echo $r->batch_number ? htmlspecialchars($r->batch_number) : '—'; ?></code></td>
                                    <td><?php echo $r->expiry_date ? date('d M Y', strtotime($r->expiry_date)) : '—'; ?></td>
                                    <td><small><?php echo htmlspecialchars($r->reason); ?></small></td>
                                    <td><small><?php echo htmlspecialchars($reqName); ?></small></td>
                                    <td><small><?php echo date('d M Y H:i', strtotime($r->created_at)); ?></small></td>
                                    <td>
                                        <button class="pa-approve-btn" onclick="paAct(<?php echo (int)$r->id; ?>,'approve')">
                                            <i class="fa fa-check"></i> Approve
                                        </button>
                                        <button class="pa-reject-btn"  onclick="paAct(<?php echo (int)$r->id; ?>,'reject')">
                                            <i class="fa fa-times"></i> Reject
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                            <div class="empty-box">
                                <i class="fa fa-check-circle text-success"></i>
                                No pending stock requests. All clear!
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- HISTORY panel -->
                <div class="pa-panel" id="panel-history">
                    <div class="pa-table-wrap">
                        <div class="pa-table-header">
                            <h4><i class="fa fa-history text-primary"></i> Request History</h4>
                            <small class="text-muted"><?php echo count($history); ?> records</small>
                        </div>
                        <div style="overflow-x:auto;">
                            <table class="pa-table">
                                <thead><tr>
                                    <th>#</th>
                                    <th>Medication</th>
                                    <th>Type</th>
                                    <th>Qty</th>
                                    <th>Status</th>
                                    <th>Requested By</th>
                                    <th>Actioned By</th>
                                    <th>Date</th>
                                    <th>Admin Notes</th>
                                </tr></thead>
                                <tbody>
                                <?php if (count($history) === 0): ?>
                                    <tr><td colspan="9" class="empty-box">
                                        <i class="fa fa-inbox"></i>No history records yet.
                                    </td></tr>
                                <?php else: ?>
                                <?php foreach ($history as $h):
                                    $reqNameH = trim((string)$h->requester_first . ' ' . (string)$h->requester_last);
                                    if ($reqNameH === '') $reqNameH = (string)$h->requester_name;
                                    $htype = strtolower((string)$h->request_type);
                                    $hTypeCls = in_array($htype, ['restock','batch_restock','adjustment','add']) ? $htype : 'other';
                                    $hstat = strtolower((string)$h->status);
                                ?>
                                <tr>
                                    <td><small class="text-muted">#<?php echo (int)$h->id; ?></small></td>
                                    <td><?php echo htmlspecialchars($h->drug_name); ?></td>
                                    <td><span class="pa-type <?php echo $hTypeCls; ?>"><?php echo strtoupper($h->request_type); ?></span></td>
                                    <td><?php echo number_format((float)$h->quantity, 0); ?></td>
                                    <td><span class="pa-status <?php echo $hstat; ?>"><?php echo strtoupper($h->status); ?></span></td>
                                    <td><small><?php echo htmlspecialchars($reqNameH); ?></small></td>
                                    <td><small><?php echo $h->approver_name ? htmlspecialchars($h->approver_name) : '—'; ?></small></td>
                                    <td><small><?php echo date('d M Y H:i', strtotime($h->created_at)); ?></small></td>
                                    <td><small><?php echo htmlspecialchars($h->admin_notes ?? ''); ?></small></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- AUDIT LOG panel -->
                <div class="pa-panel" id="panel-audit">
                    <div class="pa-table-wrap">
                        <div class="pa-table-header">
                            <h4><i class="fa fa-shield text-primary"></i> Stock Audit Log</h4>
                            <small class="text-muted"><?php echo count($audit_log); ?> entries</small>
                        </div>
                        <div style="overflow-x:auto;">
                            <table class="pa-table">
                                <thead><tr>
                                    <th>Date</th>
                                    <th>Medication</th>
                                    <th>Action</th>
                                    <th>Old Qty</th>
                                    <th>New Qty</th>
                                    <th>Change</th>
                                    <th>By</th>
                                    <th>Approved By</th>
                                    <th>Notes</th>
                                </tr></thead>
                                <tbody>
                                <?php if (count($audit_log) === 0): ?>
                                    <tr><td colspan="9" class="empty-box">
                                        <i class="fa fa-inbox"></i>No audit log entries.
                                    </td></tr>
                                <?php else: ?>
                                <?php foreach ($audit_log as $a):
                                    $oldQ = (float)($a->old_quantity ?? 0);
                                    $newQ = (float)($a->new_quantity ?? 0);
                                    $delta = $newQ - $oldQ;
                                    $deltaStr = ($delta >= 0 ? '+' : '') . number_format($delta, 0);
                                    $deltaCls = $delta >= 0 ? 'color:var(--success);' : 'color:var(--danger);';
                                ?>
                                <tr>
                                    <td><small><?php echo date('d M Y H:i', strtotime($a->created_at)); ?></small></td>
                                    <td><?php echo htmlspecialchars($a->drug_name ?? ''); ?></td>
                                    <td><span class="pa-type other"><?php echo htmlspecialchars($a->action_type ?? ''); ?></span></td>
                                    <td><?php echo number_format($oldQ, 0); ?></td>
                                    <td><?php echo number_format($newQ, 0); ?></td>
                                    <td><strong style="<?php echo $deltaCls; ?>"><?php echo $deltaStr; ?></strong></td>
                                    <td><small><?php echo htmlspecialchars($a->performer_name ?? ''); ?></small></td>
                                    <td><small><?php echo $a->approver_name ? htmlspecialchars($a->approver_name) : '—'; ?></small></td>
                                    <td><small><?php echo htmlspecialchars($a->notes ?? ''); ?></small></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                </section>
            </aside>
        </div>

        <!-- Approve/Reject Modal -->
        <div class="pa-modal-overlay" id="paModalOverlay">
            <div class="pa-modal">
                <h4 id="paModalTitle">Approve Request</h4>
                <p id="paModalDesc" class="text-muted" style="font-size:13px;margin-bottom:12px;"></p>
                <textarea id="paModalNotes" placeholder="Admin notes (optional for approval, required for rejection)…"></textarea>
                <div class="pa-modal-footer">
                    <button class="btn btn-default btn-sm" onclick="paModalClose()"><i class="fa fa-times"></i> Cancel</button>
                    <button class="btn btn-sm" id="paModalConfirm" style="font-weight:700;"><i class="fa fa-check"></i> Confirm</button>
                </div>
            </div>
        </div>

        <!-- Toast container -->
        <div class="pa-toast-wrap" id="paToastWrap"></div>

        <script src="<?php echo base_url();?>public/js/jquery.min.js"></script>
        <script src="<?php echo base_url();?>public/js/bootstrap.min.js" type="text/javascript"></script>
        <script src="<?php echo base_url();?>public/js/AdminLTE/app.js" type="text/javascript"></script>
        <script>
        (function($) {
            'use strict';
            var BASE = '<?php echo base_url(); ?>';
            var csrfName = '<?php echo $this->security->get_csrf_token_name(); ?>';
            var csrfHash = '<?php echo $this->security->get_csrf_hash(); ?>';
            var _action = '', _reqId = 0;

            /* ── Tab switching ── */
            window.paTab = function(name) {
                $('.pa-panel').removeClass('active');
                $('.pa-tab').removeClass('active');
                $('#panel-' + name).addClass('active');
                $('.pa-tab').each(function() {
                    if ($(this).attr('onclick') && $(this).attr('onclick').indexOf("'" + name + "'") >= 0) {
                        $(this).addClass('active');
                    }
                });
            };

            /* ── Toast ── */
            function paToast(msg, type) {
                var t = $('<div class="pa-toast ' + (type||'info') + '">' + $('<span>').text(msg).html() + '</div>');
                $('#paToastWrap').append(t);
                setTimeout(function() { t.fadeOut(400, function() { $(this).remove(); }); }, 4500);
            }

            /* ── Modal ── */
            window.paAct = function(reqId, action) {
                _reqId  = reqId;
                _action = action;
                $('#paModalNotes').val('');
                if (action === 'approve') {
                    $('#paModalTitle').text('Approve Request #' + reqId);
                    $('#paModalDesc').text('You are about to approve this stock request. Stock will be updated immediately.');
                    $('#paModalConfirm').removeClass('btn-danger').addClass('btn-success').html('<i class="fa fa-check"></i> Approve');
                } else {
                    $('#paModalTitle').text('Reject Request #' + reqId);
                    $('#paModalDesc').text('Please provide a reason for rejection (required).');
                    $('#paModalConfirm').removeClass('btn-success').addClass('btn-danger').html('<i class="fa fa-times"></i> Reject');
                }
                $('#paModalOverlay').addClass('show');
                setTimeout(function() { $('#paModalNotes').focus(); }, 80);
            };

            window.paModalClose = function() {
                $('#paModalOverlay').removeClass('show');
            };

            $('#paModalConfirm').on('click', function() {
                var notes = $('#paModalNotes').val().trim();
                if (_action === 'reject' && notes === '') {
                    $('#paModalNotes').css('border-color','var(--danger)');
                    paToast('Rejection reason is required.', 'error');
                    return;
                }
                $('#paModalNotes').css('border-color','');
                var $btn = $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Processing…');
                var url  = BASE + 'app/stock_approval/' + _action;
                var postData = { request_id: _reqId, admin_notes: notes };
                postData[csrfName] = csrfHash;
                $.post(url, postData)
                    .done(function() {
                        paModalClose();
                        $('#pendingRow' + _reqId).fadeOut(350, function() {
                            $(this).remove();
                            var remaining = $('#pendingTbody tr:visible').length;
                            $('#pendingCountNum, #pendingBadge').text(remaining);
                            if (remaining === 0) {
                                $('#pendingTbody').closest('.pa-table-wrap').find('div[style*="overflow"]').html(
                                    '<div class="empty-box"><i class="fa fa-check-circle text-success"></i>No pending stock requests. All clear!</div>'
                                );
                            }
                        });
                        paToast('Request #' + _reqId + ' ' + _action + 'd successfully.', 'success');
                    })
                    .fail(function() {
                        paToast('Failed to ' + _action + ' request — please try again.', 'error');
                    })
                    .always(function() {
                        $btn.prop('disabled', false).html(_action === 'approve'
                            ? '<i class="fa fa-check"></i> Approve'
                            : '<i class="fa fa-times"></i> Reject');
                    });
            });

            /* Close modal on overlay click */
            $('#paModalOverlay').on('click', function(e) {
                if ($(e.target).is('#paModalOverlay')) paModalClose();
            });

        })(jQuery);
        </script>
    </body>
</html>
