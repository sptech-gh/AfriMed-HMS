<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Pharmacy Returns</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
    <style>
        :root {
            --danger:  #dd4b39; --warning: #f39c12; --success: #00a65a;
            --primary: #3c8dbc; --shadow: 0 2px 10px rgba(0,0,0,0.09); --radius: 8px;
        }
        .ret-stat { background:#fff; border-radius:var(--radius); box-shadow:var(--shadow);
            padding:16px 18px; display:flex; align-items:center; gap:14px;
            border-left:4px solid #ddd; margin-bottom:16px; }
        .ret-stat .rn  { font-size:28px; font-weight:700; line-height:1; }
        .ret-stat .rl  { font-size:11px; color:#888; text-transform:uppercase; letter-spacing:.4px; margin-top:3px; }
        .ret-stat .ri  { font-size:32px; opacity:.15; margin-left:auto; }
        .ret-filter { background:#fff; border-radius:var(--radius); box-shadow:var(--shadow);
            padding:14px 18px; margin-bottom:18px; display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
        .ret-filter input, .ret-filter select { height:34px; border:1.5px solid #ddd; border-radius:6px;
            padding:4px 10px; font-size:13px; color:#333; }
        .ret-filter input:focus, .ret-filter select:focus { outline:none; border-color:var(--primary); }
        .ret-table-wrap { background:#fff; border-radius:var(--radius); box-shadow:var(--shadow); overflow:hidden; margin-bottom:18px; }
        .ret-table-hdr  { padding:14px 18px; border-bottom:1px solid #f0f0f0;
            display:flex; justify-content:space-between; align-items:center; }
        .ret-table-hdr h4 { margin:0; font-weight:700; font-size:15px; }
        .ret-table { width:100%; border-collapse:collapse; font-size:13px; }
        .ret-table thead th { background:#f5f7fa; padding:10px 12px; font-weight:700;
            text-align:left; border-bottom:2px solid #e0e0e0; white-space:nowrap; }
        .ret-table tbody tr { border-bottom:1px solid #f2f2f2; transition:background .15s; }
        .ret-table tbody tr:hover { background:#f7fbff; }
        .ret-table td { padding:10px 12px; vertical-align:middle; }
        .ret-table tr.pending-row { border-left:4px solid var(--warning); }
        .ret-table tr.approved-row { border-left:4px solid var(--success); }
        .ret-table tr.rejected-row { border-left:4px solid var(--danger); }
        .ret-badge { display:inline-block; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:700; }
        .ret-badge.PENDING  { background:#fff3cd; color:#856404; }
        .ret-badge.APPROVED { background:#d4edda; color:#155724; }
        .ret-badge.REJECTED { background:#f8d7da; color:#721c24; }
        .type-badge { display:inline-block; padding:2px 9px; border-radius:10px; font-size:11px; font-weight:700; }
        .type-badge.PATIENT_RETURN      { background:#d1ecf1; color:#0c5460; }
        .type-badge.WARD_RETURN         { background:#fff3cd; color:#856404; }
        .type-badge.INTERNAL_CORRECTION { background:#e9ecef; color:#495057; }
    </style>
</head>
<body class="skin-blue">
    <?php require_once(APPPATH.'views/include/header.php');?>
    <div class="wrapper row-offcanvas row-offcanvas-left">
        <?php require_once(APPPATH.'views/include/sidebar.php');?>
        <aside class="right-side">
            <section class="content-header">
                <h1><i class="fa fa-undo"></i> Pharmacy Returns <small>Drug return management</small></h1>
                <ol class="breadcrumb">
                    <li><a href="<?php echo base_url(); ?>app/pharmacy"><i class="fa fa-medkit"></i> Pharmacy</a></li>
                    <li class="active">Returns</li>
                </ol>
            </section>

            <section class="content">
                <?php if (isset($message) && $message) { echo $message; } ?>

                <?php
                    $summary = isset($summary) ? $summary : array();
                    $returns = isset($returns) && is_array($returns) ? $returns : array();
                    $filters = isset($filters) ? $filters : array();
                    $reasons = array(
                        'OVER_DISPENSED'          => 'Over-dispensed',
                        'PATIENT_REFUSED'         => 'Patient refused',
                        'WRONG_DRUG'              => 'Wrong drug',
                        'EXPIRED_DRUG'            => 'Expired',
                        'DAMAGED_DRUG'            => 'Damaged',
                        'PRESCRIPTION_CANCELLED'  => 'Rx cancelled',
                        'ADVERSE_REACTION'        => 'Adverse reaction',
                        'OTHER'                   => 'Other',
                    );
                ?>

                <!-- Stat cards -->
                <div class="row">
                    <div class="col-md-3 col-sm-6">
                        <div class="ret-stat" style="border-left-color:var(--warning);">
                            <div>
                                <div class="rn text-warning"><?php echo isset($summary['pending']) ? (int)$summary['pending'] : 0; ?></div>
                                <div class="rl">Pending</div>
                            </div>
                            <i class="fa fa-clock-o ri text-warning"></i>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="ret-stat" style="border-left-color:var(--success);">
                            <div>
                                <div class="rn text-success"><?php echo isset($summary['approved']) ? (int)$summary['approved'] : 0; ?></div>
                                <div class="rl">Approved</div>
                            </div>
                            <i class="fa fa-check ri text-success"></i>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="ret-stat" style="border-left-color:var(--danger);">
                            <div>
                                <div class="rn text-danger"><?php echo isset($summary['rejected']) ? (int)$summary['rejected'] : 0; ?></div>
                                <div class="rl">Rejected</div>
                            </div>
                            <i class="fa fa-times ri text-danger"></i>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="ret-stat" style="border-left-color:var(--primary);">
                            <div>
                                <div class="rn text-primary"><?php echo isset($summary['today']) ? (int)$summary['today'] : 0; ?></div>
                                <div class="rl">Today</div>
                            </div>
                            <i class="fa fa-calendar ri text-primary"></i>
                        </div>
                    </div>
                </div>

                <!-- Filter bar -->
                <form method="get" action="<?php echo base_url(); ?>app/pharmacy/pharmacy_returns">
                    <div class="ret-filter">
                        <input type="text" name="search" placeholder="Search patient / drug…"
                               value="<?php echo htmlspecialchars($filters['search'] ?? ''); ?>">
                        <select name="status">
                            <option value="">All Statuses</option>
                            <option value="PENDING"  <?php echo ($filters['status'] ?? '') === 'PENDING'  ? 'selected' : ''; ?>>Pending</option>
                            <option value="APPROVED" <?php echo ($filters['status'] ?? '') === 'APPROVED' ? 'selected' : ''; ?>>Approved</option>
                            <option value="REJECTED" <?php echo ($filters['status'] ?? '') === 'REJECTED' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                        <select name="return_type">
                            <option value="">All Types</option>
                            <option value="PATIENT_RETURN"      <?php echo ($filters['return_type'] ?? '') === 'PATIENT_RETURN'      ? 'selected' : ''; ?>>Patient Return</option>
                            <option value="WARD_RETURN"         <?php echo ($filters['return_type'] ?? '') === 'WARD_RETURN'         ? 'selected' : ''; ?>>Ward Return</option>
                            <option value="INTERNAL_CORRECTION" <?php echo ($filters['return_type'] ?? '') === 'INTERNAL_CORRECTION' ? 'selected' : ''; ?>>Internal Correction</option>
                        </select>
                        <input type="date" name="date_from" value="<?php echo htmlspecialchars($filters['date_from'] ?? ''); ?>" title="From date">
                        <input type="date" name="date_to"   value="<?php echo htmlspecialchars($filters['date_to']   ?? ''); ?>" title="To date">
                        <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-search"></i> Filter</button>
                        <a href="<?php echo base_url(); ?>app/pharmacy/pharmacy_returns" class="btn btn-default btn-sm"><i class="fa fa-refresh"></i> Reset</a>
                        <a href="<?php echo base_url(); ?>app/pharmacy/create_return" class="btn btn-success btn-sm" style="margin-left:auto;">
                            <i class="fa fa-plus"></i> New Return
                        </a>
                    </div>
                </form>

                <!-- Returns table -->
                <div class="ret-table-wrap">
                    <div class="ret-table-hdr">
                        <h4><i class="fa fa-undo text-primary"></i> Returns List
                            <small class="text-muted" style="font-weight:400;font-size:12px;margin-left:8px;"><?php echo count($returns); ?> records</small>
                        </h4>
                    </div>
                    <div style="overflow-x:auto;">
                        <table class="ret-table">
                            <thead><tr>
                                <th>Return #</th>
                                <th>Date</th>
                                <th>Patient</th>
                                <th>Drug</th>
                                <th>Qty</th>
                                <th>Type</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th>Requested By</th>
                                <th style="width:100px;">Actions</th>
                            </tr></thead>
                            <tbody>
                            <?php if (count($returns) === 0): ?>
                                <tr><td colspan="10" style="text-align:center;padding:36px;color:#aaa;">
                                    <i class="fa fa-inbox" style="font-size:32px;display:block;margin-bottom:8px;"></i>
                                    No returns found.
                                </td></tr>
                            <?php else: ?>
                            <?php foreach ($returns as $r):
                                $rStatus  = strtoupper(isset($r->status) ? (string)$r->status : 'PENDING');
                                $rType    = isset($r->return_type) ? (string)$r->return_type : 'PATIENT_RETURN';
                                $rowCls   = strtolower($rStatus) . '-row';
                            ?>
                            <tr class="<?php echo $rowCls; ?>">
                                <td><code><?php echo htmlspecialchars($r->return_number); ?></code></td>
                                <td><small><?php echo date('d M Y', strtotime($r->return_date)); ?></small></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($r->patient_name ?? ''); ?></strong>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($r->patient_no ?? ''); ?></small>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($r->drug_name ?? ''); ?>
                                    <?php if (!empty($r->generic_name)): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($r->generic_name); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;"><strong><?php echo number_format((float)($r->quantity_returned ?? 0), 0); ?></strong></td>
                                <td><span class="type-badge <?php echo $rType; ?>"><?php
                                    $typeLabels = ['PATIENT_RETURN' => 'Patient', 'WARD_RETURN' => 'Ward', 'INTERNAL_CORRECTION' => 'Internal'];
                                    echo isset($typeLabels[$rType]) ? $typeLabels[$rType] : htmlspecialchars($rType);
                                ?></span></td>
                                <td><small><?php
                                    $rr = isset($r->return_reason) ? (string)$r->return_reason : '';
                                    echo htmlspecialchars(isset($reasons[$rr]) ? $reasons[$rr] : $rr);
                                ?></small></td>
                                <td><span class="ret-badge <?php echo $rStatus; ?>"><?php echo $rStatus; ?></span></td>
                                <td><small><?php echo htmlspecialchars($r->requested_by_name ?? $r->requested_by ?? ''); ?></small></td>
                                <td>
                                    <a href="<?php echo base_url(); ?>app/pharmacy/view_return/<?php echo (int)$r->return_id; ?>"
                                       class="btn btn-xs btn-info" title="View"><i class="fa fa-eye"></i></a>
                                    <?php if ($rStatus === 'PENDING' && (function_exists('has_role') && (has_role('admin') || has_role('pharmacist')))): ?>
                                    <button class="btn btn-xs btn-success btn-approve"
                                            data-id="<?php echo (int)$r->return_id; ?>"
                                            data-number="<?php echo htmlspecialchars($r->return_number); ?>"
                                            title="Approve"><i class="fa fa-check"></i></button>
                                    <button class="btn btn-xs btn-danger btn-reject"
                                            data-id="<?php echo (int)$r->return_id; ?>"
                                            data-number="<?php echo htmlspecialchars($r->return_number); ?>"
                                            title="Reject"><i class="fa fa-times"></i></button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </section>
        </aside>
    </div>

    <!-- Approve Modal -->
    <div class="modal fade" id="approveModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" action="<?php echo base_url(); ?>app/pharmacy/approve_return">
                    <div class="modal-header" style="background:var(--success);color:#fff;">
                        <button type="button" class="close" data-dismiss="modal" style="color:#fff;">&times;</button>
                        <h4 class="modal-title"><i class="fa fa-check"></i> Approve Return</h4>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="return_id" id="approve_return_id">
                        <p>Approve return <strong id="approve_return_number"></strong>?</p>
                        <p style="color:var(--success);font-size:13px;"><i class="fa fa-info-circle"></i> Stock will be increased upon approval.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success"><i class="fa fa-check"></i> Approve</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reject Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" action="<?php echo base_url(); ?>app/pharmacy/reject_return">
                    <div class="modal-header" style="background:var(--danger);color:#fff;">
                        <button type="button" class="close" data-dismiss="modal" style="color:#fff;">&times;</button>
                        <h4 class="modal-title"><i class="fa fa-times"></i> Reject Return</h4>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="return_id" id="reject_return_id">
                        <p>Reject return <strong id="reject_return_number"></strong>?</p>
                        <div class="form-group">
                            <label style="font-size:12px;color:#888;text-transform:uppercase;">Rejection Reason <span class="text-danger">*</span></label>
                            <textarea name="rejection_reason" class="form-control" rows="3" required
                                      placeholder="Enter reason for rejection…"></textarea>
                        </div>
                        <p style="color:var(--warning);font-size:13px;"><i class="fa fa-warning"></i> Stock will NOT be adjusted.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger"><i class="fa fa-times"></i> Reject</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="<?php echo base_url();?>public/js/jquery.min.js"></script>
    <script src="<?php echo base_url();?>public/js/bootstrap.min.js"></script>
    <script src="<?php echo base_url();?>public/js/AdminLTE/app.js"></script>
    <script>
    $(function() {
        $('.btn-approve').on('click', function() {
            $('#approve_return_id').val($(this).data('id'));
            $('#approve_return_number').text($(this).data('number'));
            $('#approveModal').modal('show');
        });
        $('.btn-reject').on('click', function() {
            $('#reject_return_id').val($(this).data('id'));
            $('#reject_return_number').text($(this).data('number'));
            $('#rejectModal').modal('show');
        });
    });
    </script>
</body>
</html>
