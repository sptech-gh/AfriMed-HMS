<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo isset($companyInfo->site_title) ? $companyInfo->site_title : 'HMS'; ?> — Smart Billing</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
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
<style>
.visit-badge { font-size:11px; padding:3px 7px; border-radius:10px; font-weight:600; }
.fee-row { display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid #f0f0f0; }
.fee-row:last-child { border-bottom:none; font-weight:700; font-size:15px; }
.waived-text { text-decoration:line-through; color:#aaa; margin-right:6px; }
.badge-waived { background:#27ae60; color:#fff; font-size:10px; padding:2px 6px; border-radius:8px; }
.queue-row:hover { background:#f9f9f9; cursor:pointer; }
</style>

<div class="content-wrapper">
  <section class="content-header">
    <h1><i class="fa fa-bolt"></i> Smart Billing <small>GHS 1-Click Cashier Billing</small></h1>
    <ol class="breadcrumb">
      <li><a href="<?php echo base_url(); ?>app/dashboard"><i class="fa fa-dashboard"></i> Dashboard</a></li>
      <li class="active">Smart Billing</li>
    </ol>
  </section>

  <section class="content">
    <?php if (!empty($message)) echo $message; ?>

    <!-- Summary Cards -->
    <div class="row">
      <div class="col-md-3 col-sm-6">
        <div class="info-box bg-yellow">
          <span class="info-box-icon"><i class="fa fa-clock-o"></i></span>
          <div class="info-box-content">
            <span class="info-box-text">Pending Billing</span>
            <span class="info-box-number"><?php echo (int)$pending_count; ?></span>
          </div>
        </div>
      </div>
      <div class="col-md-3 col-sm-6">
        <div class="info-box bg-green">
          <span class="info-box-icon"><i class="fa fa-check-circle"></i></span>
          <div class="info-box-content">
            <span class="info-box-text">Billed Today</span>
            <span class="info-box-number"><?php echo (int)$billed_today; ?></span>
          </div>
        </div>
      </div>
      <div class="col-md-3 col-sm-6">
        <div class="info-box bg-blue">
          <span class="info-box-icon"><i class="fa fa-tag"></i></span>
          <div class="info-box-content">
            <span class="info-box-text">Waivers Today</span>
            <span class="info-box-number"><?php echo (int)$waivers_today; ?></span>
          </div>
        </div>
      </div>
      <div class="col-md-3 col-sm-6">
        <div class="info-box bg-aqua">
          <span class="info-box-icon"><i class="fa fa-calendar"></i></span>
          <div class="info-box-content">
            <span class="info-box-text">Date</span>
            <span class="info-box-number" style="font-size:16px;"><?php echo date('d M Y', strtotime($date)); ?></span>
          </div>
        </div>
      </div>
    </div>

    <div class="row">
      <!-- Pending Billing Queue -->
      <div class="col-md-8">
        <div class="box box-warning">
          <div class="box-header with-border">
            <h3 class="box-title"><i class="fa fa-list"></i> Pending Billing Queue
              <span class="badge bg-yellow" style="margin-left:8px;"><?php echo (int)$pending_count; ?></span>
            </h3>
            <div class="box-tools pull-right">
              <form method="get" action="" class="form-inline" style="margin:0;">
                <input type="date" name="date" value="<?php echo htmlspecialchars($date); ?>" class="form-control input-sm" onchange="this.form.submit()">
              </form>
            </div>
          </div>
          <div class="box-body no-padding">
            <?php if (empty($pending_queue)): ?>
              <div class="text-center" style="padding:30px;color:#aaa;">
                <i class="fa fa-check-circle fa-2x"></i><br>
                No pending billing entries for this date.
              </div>
            <?php else: ?>
            <div class="table-responsive">
            <table class="table table-hover table-condensed" id="pendingTable">
              <thead>
                <tr>
                  <th>Patient</th>
                  <th>Visit Type</th>
                  <th>OPD No</th>
                  <th>Time</th>
                  <th style="text-align:right;">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($pending_queue as $row):
                  $badgeClass = array(
                    'FIRST_VISIT'        => 'label-primary',
                    'REVIEW'             => 'label-success',
                    'FOLLOW_UP'          => 'label-info',
                    'WALK_IN'            => 'label-default',
                    'MISSED_APPOINTMENT' => 'label-warning',
                    'EMERGENCY'          => 'label-danger',
                  );
                  $bClass = isset($badgeClass[$row->visit_type]) ? $badgeClass[$row->visit_type] : 'label-default';
                  $vLabel = str_replace('_', ' ', $row->visit_type);
                ?>
                <tr class="queue-row" data-iop="<?php echo htmlspecialchars($row->iop_id); ?>"
                    data-patient="<?php echo htmlspecialchars($row->patient_no); ?>"
                    data-name="<?php echo htmlspecialchars($row->patient_name); ?>">
                  <td>
                    <strong><?php echo htmlspecialchars($row->patient_name); ?></strong><br>
                    <small class="text-muted"><?php echo htmlspecialchars($row->patient_no); ?></small>
                  </td>
                  <td>
                    <span class="label <?php echo $bClass; ?> visit-badge"><?php echo $vLabel; ?></span>
                    <?php if ($row->consultation_waived): ?>
                      <br><span class="badge-waived" style="display:inline-block;margin-top:3px;background:#27ae60;color:#fff;font-size:10px;padding:2px 6px;border-radius:8px;">Consult Waived</span>
                    <?php endif; ?>
                  </td>
                  <td><code><?php echo htmlspecialchars($row->iop_id); ?></code></td>
                  <td><small><?php echo date('H:i', strtotime($row->created_at)); ?></small></td>
                  <td style="text-align:right;">
                    <button class="btn btn-sm btn-warning btn-preview-billing"
                            data-iop="<?php echo htmlspecialchars($row->iop_id); ?>"
                            data-patient="<?php echo htmlspecialchars($row->patient_no); ?>"
                            data-name="<?php echo htmlspecialchars($row->patient_name); ?>">
                      <i class="fa fa-bolt"></i> 1-Click Bill
                    </button>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Billed Today -->
        <div class="box box-success">
          <div class="box-header with-border">
            <h3 class="box-title"><i class="fa fa-check-circle"></i> Billed Today
              <span class="badge bg-green" style="margin-left:8px;"><?php echo (int)$billed_today; ?></span>
            </h3>
            <div class="box-tools pull-right">
              <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button>
            </div>
          </div>
          <div class="box-body no-padding">
            <?php if (empty($billed_queue)): ?>
              <div class="text-center" style="padding:20px;color:#aaa;"><i class="fa fa-info-circle"></i> No bills processed yet today.</div>
            <?php else: ?>
            <div class="table-responsive">
            <table class="table table-condensed table-striped">
              <thead>
                <tr>
                  <th>Patient</th>
                  <th>Visit Type</th>
                  <th>Reg Fee</th>
                  <th>Consult Fee</th>
                  <th>Total</th>
                  <th>Time</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($billed_queue as $r):
                  $total = (float)$r->registration_fee + (float)$r->consultation_fee;
                ?>
                <tr>
                  <td>
                    <strong><?php echo htmlspecialchars($r->patient_name); ?></strong><br>
                    <small class="text-muted"><?php echo htmlspecialchars($r->patient_no); ?></small>
                  </td>
                  <td><span class="label label-default visit-badge"><?php echo str_replace('_',' ',$r->visit_type); ?></span></td>
                  <td>GHS <?php echo number_format((float)$r->registration_fee, 2); ?></td>
                  <td>
                    <?php if ($r->consultation_waived): ?>
                      <span class="waived-text">GHS <?php echo number_format((float)$r->consultation_fee, 2); ?></span>
                      <span class="label label-success">Waived</span>
                    <?php else: ?>
                      GHS <?php echo number_format((float)$r->consultation_fee, 2); ?>
                    <?php endif; ?>
                  </td>
                  <td><strong>GHS <?php echo number_format($total, 2); ?></strong></td>
                  <td><small><?php echo $r->billed_at ? date('H:i', strtotime($r->billed_at)) : '—'; ?></small></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Config Panel -->
      <div class="col-md-4">
        <div class="box box-primary">
          <div class="box-header with-border">
            <h3 class="box-title"><i class="fa fa-cog"></i> GHS Fee Configuration</h3>
            <div class="box-tools pull-right">
              <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button>
            </div>
          </div>
          <div class="box-body">
            <?php
            $cfg = array();
            if (!empty($config)) { foreach ($config as $c) { $cfg[$c->config_key] = $c->config_value; } }
            $cfgGet = function($key, $default='') use ($cfg) { return isset($cfg[$key]) ? $cfg[$key] : $default; };
            ?>
            <form method="post" action="<?php echo base_url(); ?>app/billing/smart_billing_config_save">
              <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
              <?php if (function_exists('has_role') && has_role('admin')): ?>
              <div class="form-group">
                <label>Auto-Bill Visit Fees</label>
                <select name="auto_bill_visit_fees" class="form-control input-sm">
                  <?php $abv = (string)$cfgGet('auto_bill_visit_fees','1'); ?>
                  <option value="1" <?php echo ((string)$abv === '1') ? 'selected' : ''; ?>>Enabled</option>
                  <option value="0" <?php echo ((string)$abv === '0') ? 'selected' : ''; ?>>Disabled</option>
                </select>
              </div>
              <div class="form-group">
                <label>Enable Registration Fee</label>
                <select name="enable_registration_fee" class="form-control input-sm">
                  <?php $erf = (string)$cfgGet('enable_registration_fee','1'); ?>
                  <option value="1" <?php echo ((string)$erf === '1') ? 'selected' : ''; ?>>Enabled</option>
                  <option value="0" <?php echo ((string)$erf === '0') ? 'selected' : ''; ?>>Disabled</option>
                </select>
              </div>
              <div class="form-group">
                <label>Enable Consultation Fee</label>
                <select name="enable_consultation_fee" class="form-control input-sm">
                  <?php $ecf = (string)$cfgGet('enable_consultation_fee','1'); ?>
                  <option value="1" <?php echo ((string)$ecf === '1') ? 'selected' : ''; ?>>Enabled</option>
                  <option value="0" <?php echo ((string)$ecf === '0') ? 'selected' : ''; ?>>Disabled</option>
                </select>
              </div>
              <div class="form-group">
                <label>Registration Fee Item ID (bill_particular.particular_id)</label>
                <input type="number" min="0" name="registration_fee_item_id" id="registration_fee_item_id" class="form-control input-sm" value="<?php echo htmlspecialchars($cfgGet('registration_fee_item_id','0')); ?>">
              </div>
              <div class="form-group">
                <label>Consultation Fee Item ID (bill_particular.particular_id)</label>
                <input type="number" min="0" name="consultation_fee_item_id" id="consultation_fee_item_id" class="form-control input-sm" value="<?php echo htmlspecialchars($cfgGet('consultation_fee_item_id','0')); ?>">
              </div>
              
                            <div class="form-group">
                <label>Detention Fee Item ID (bill_particular.particular_id)</label>
                <input type="number" min="0" name="detention_fee_item_id" id="detention_fee_item_id" class="form-control input-sm" value="<?php echo htmlspecialchars($cfgGet('detention_fee_item_id','0')); ?>">
              </div>
              <div class="form-group">
                
                <button type="button" class="btn btn-default btn-sm" id="btnDetectVisitFeeItems" style="width:100%;">
                  <i class="fa fa-search"></i> Find Fee Item IDs
                </button>
              </div>
              
              <div class="form-group">
                <label>Registration Fee — Cash (GHS)</label>
                <input type="number" step="0.01" min="0" name="registration_fee_cash" class="form-control input-sm" value="<?php echo htmlspecialchars($cfgGet('registration_fee_cash','20.00')); ?>">
              </div>
              <div class="form-group">
                <label>Registration Fee — NHIS (GHS)</label>
                <input type="number" step="0.01" min="0" name="registration_fee_nhis" class="form-control input-sm" value="<?php echo htmlspecialchars($cfgGet('registration_fee_nhis','0.00')); ?>">
              </div>
              <div class="form-group">
                <label>Consultation Fee — Cash (GHS)</label>
                <input type="number" step="0.01" min="0" name="consultation_fee_cash" class="form-control input-sm" value="<?php echo htmlspecialchars($cfgGet('consultation_fee_cash','30.00')); ?>">
              </div>
              <div class="form-group">
                <label>Consultation Fee — NHIS (GHS)</label>
                <input type="number" step="0.01" min="0" name="consultation_fee_nhis" class="form-control input-sm" value="<?php echo htmlspecialchars($cfgGet('consultation_fee_nhis','0.00')); ?>">
              </div>
                            <div class="form-group">
                <label>Detention Fee — Cash (GHS)</label>
                <input type="number" step="0.01" min="0" name="detention_fee_cash" class="form-control input-sm" value="<?php echo htmlspecialchars($cfgGet('detention_fee_cash','0.00')); ?>">
              </div>
              <div class="form-group">
                <label>Detention Fee — NHIS (GHS)</label>
                <input type="number" step="0.01" min="0" name="detention_fee_nhis" class="form-control input-sm" value="<?php echo htmlspecialchars($cfgGet('detention_fee_nhis','0.00')); ?>">
              </div>
              <div class="form-group">
                <label>Review Window (days)</label>
                <input type="number" min="0" name="review_window_days" class="form-control input-sm" value="<?php echo htmlspecialchars($cfgGet('review_window_days','7')); ?>">
                <p class="help-block">Return within this many days = free follow-up</p>
              </div>
              <div class="form-group">
                <label>Appointment Grace Period (days)</label>
                <input type="number" min="0" name="missed_appt_grace_days" class="form-control input-sm" value="<?php echo htmlspecialchars($cfgGet('missed_appt_grace_days','1')); ?>">
              </div>
              <button type="submit" class="btn btn-primary btn-block"><i class="fa fa-save"></i> Save Configuration</button>
              <?php else: ?>
              <div class="table-responsive">
                <table class="table table-condensed">
                  <tr><td>Auto-Bill Visit Fees</td><td><strong><?php echo ((string)$cfgGet('auto_bill_visit_fees','1') === '1') ? 'Enabled' : 'Disabled'; ?></strong></td></tr>
                  <tr><td>Registration Fee Enabled</td><td><strong><?php echo ((string)$cfgGet('enable_registration_fee','1') === '1') ? 'Yes' : 'No'; ?></strong></td></tr>
                  <tr><td>Consultation Fee Enabled</td><td><strong><?php echo ((string)$cfgGet('enable_consultation_fee','1') === '1') ? 'Yes' : 'No'; ?></strong></td></tr>
                  <tr><td>Registration (Cash)</td><td><strong>GHS <?php echo number_format((float)$cfgGet('registration_fee_cash','20'), 2); ?></strong></td></tr>
                  <tr><td>Registration (NHIS)</td><td><strong>GHS <?php echo number_format((float)$cfgGet('registration_fee_nhis','0'), 2); ?></strong></td></tr>
                  <tr><td>Consultation (Cash)</td><td><strong>GHS <?php echo number_format((float)$cfgGet('consultation_fee_cash','30'), 2); ?></strong></td></tr>
                  <tr><td>Consultation (NHIS)</td><td><strong>GHS <?php echo number_format((float)$cfgGet('consultation_fee_nhis','0'), 2); ?></strong></td></tr>
                  <tr><td>Review Window</td><td><strong><?php echo htmlspecialchars($cfgGet('review_window_days','7')); ?> days</strong></td></tr>
                </table>
              </div>
              <?php endif; ?>
            </form>
          </div>
        </div>

        <!-- Visit Type Legend -->
        <div class="box box-default">
          <div class="box-header with-border">
            <h3 class="box-title"><i class="fa fa-info-circle"></i> GHS Billing Rules</h3>
          </div>
          <div class="box-body" style="font-size:13px;">
            <div class="table-responsive">
            <table class="table table-condensed">
              <tr>
                <td><span class="label label-primary">First Visit</span></td>
                <td>Registration + Consultation <strong>Billed</strong></td>
              </tr>
              <tr>
                <td><span class="label label-default">Walk-In</span></td>
                <td>Consultation <strong>Billed</strong></td>
              </tr>
              <tr>
                <td><span class="label label-success">Review</span></td>
                <td>Consultation <strong>Waived</strong> <small class="text-muted">(doctor-authorized only)</small></td>
              </tr>
              <tr>
                <td><span class="label label-info">Follow-Up</span></td>
                <td>Consultation <strong>Waived</strong> <small class="text-muted">(if doctor review authorization is active)</small></td>
              </tr>
              <tr>
                <td><span class="label label-warning">Missed Appt.</span></td>
                <td>Consultation <strong>Billed</strong></td>
              </tr>
            </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</div>

<!-- 1-Click Billing Preview Modal -->
<div class="modal fade" id="billingPreviewModal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-md" role="document">
    <div class="modal-content">
      <div class="modal-header" style="background:#f39c12;color:#fff;">
        <button type="button" class="close" data-dismiss="modal" style="color:#fff;opacity:1;">&times;</button>
        <h4 class="modal-title"><i class="fa fa-bolt"></i> 1-Click Billing Preview</h4>
      </div>
      <div class="modal-body" id="billingPreviewBody">
        <div class="text-center" style="padding:20px;"><i class="fa fa-spinner fa-spin fa-2x"></i></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-warning btn-lg" id="btnConfirmBilling" disabled>
          <i class="fa fa-bolt"></i> Confirm &amp; Generate Bill
        </button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="visitFeeItemModal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-md" role="document">
    <div class="modal-content">
      <div class="modal-header" style="background:#3c8dbc;color:#fff;">
        <button type="button" class="close" data-dismiss="modal" style="color:#fff;opacity:1;">&times;</button>
        <h4 class="modal-title"><i class="fa fa-cog"></i> Visit Fee Item IDs</h4>
      </div>
      <div class="modal-body">
        <div id="visitFeeItemMsg"></div>
        <div id="visitFeeItemBody" class="text-center" style="padding:20px;"><i class="fa fa-spinner fa-spin fa-2x"></i></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary" id="btnApplyVisitFeeItems" disabled>
          <i class="fa fa-check"></i> Apply IDs
        </button>
      </div>
    </div>
  </div>
</div>

<script>
var BILLING_PREVIEW_URL  = '<?php echo base_url(); ?>app/billing/smart_billing_preview';
var ONE_CLICK_BILLING_URL = '<?php echo base_url(); ?>app/billing/one_click_billing';
var VISIT_FEE_ITEM_CANDIDATES_URL = '<?php echo base_url(); ?>app/billing/smart_billing_visit_fee_item_candidates';
var VISIT_FEE_ITEM_APPLY_URL = '<?php echo base_url(); ?>app/billing/smart_billing_apply_visit_fee_item_ids';
var csrfName = '<?php echo $this->security->get_csrf_token_name(); ?>';
var csrfHash = '<?php echo $this->security->get_csrf_hash(); ?>';
var currentIopId     = '';
var currentPatientNo = '';

function _escapeHtml(s) {
    return $('<div>').text(s || '').html();
}

function _renderFeeItemList(title, items, selectedId, inputName) {
    var html = '<div style="margin-bottom:10px;">'
        + '<strong>' + _escapeHtml(title) + '</strong>'
        + '</div>';

    if (!items || !items.length) {
        html += '<div class="alert alert-warning" style="padding:6px 10px;">No matches found in bill_particular.</div>';
        return html;
    }

    html += '<div class="list-group" style="max-height:220px;overflow:auto;">';
    for (var i=0; i<items.length; i++) {
        var it = items[i];
        var id = parseInt(it.id, 10) || 0;
        var name = it.name || '';
        var g = it.group_name || '';
        var checked = (selectedId && id === selectedId) ? 'checked' : '';
        html += '<label class="list-group-item" style="font-weight:normal;">'
            + '<input type="radio" name="' + inputName + '" value="' + id + '" style="margin-right:8px;" ' + checked + '> '
            + '<code>' + id + '</code> &nbsp; ' + _escapeHtml(name)
            + (g ? ('<br><small class="text-muted">' + _escapeHtml(g) + '</small>') : '')
            + '</label>';
    }
    html += '</div>';
    return html;
}

$(document).on('click', '#btnDetectVisitFeeItems', function() {
    $('#visitFeeItemMsg').html('');
    $('#visitFeeItemBody').html('<div class="text-center" style="padding:20px;"><i class="fa fa-spinner fa-spin fa-2x"></i><br>Searching bill particulars...</div>');
    $('#btnApplyVisitFeeItems').prop('disabled', true);
    $('#visitFeeItemModal').modal('show');

    var req = {}; req[csrfName] = csrfHash;
    $.post(VISIT_FEE_ITEM_CANDIDATES_URL, req, function(res) {
        if (!res || !res.success) {
            $('#visitFeeItemBody').html('<div class="alert alert-danger">' + _escapeHtml((res && res.error) ? res.error : 'Unable to load candidates') + '</div>');
            return;
        }
        if (res.csrf_hash) { csrfHash = res.csrf_hash; }

        var d = res.data || {};
        var reg = d.registration || { candidates: [], suggested_id: 0 };
        var con = d.consultation || { candidates: [], suggested_id: 0 };

        var currentReg = parseInt($('#registration_fee_item_id').val(), 10) || 0;
        var currentCon = parseInt($('#consultation_fee_item_id').val(), 10) || 0;

        var regSel = currentReg > 0 ? currentReg : (parseInt(reg.suggested_id, 10) || 0);
        var conSel = currentCon > 0 ? currentCon : (parseInt(con.suggested_id, 10) || 0);

        var html = '';
        html += _renderFeeItemList('Registration Fee Candidates', reg.candidates, regSel, 'reg_item');
        html += '<hr style="margin:12px 0;">';
        html += _renderFeeItemList('Consultation Fee Candidates', con.candidates, conSel, 'con_item');
        $('#visitFeeItemBody').html(html);

        var hasReg = $('input[name="reg_item"]:checked').length > 0;
        var hasCon = $('input[name="con_item"]:checked').length > 0;
        $('#btnApplyVisitFeeItems').prop('disabled', !(hasReg && hasCon));

        if (regSel > 0) { $('input[name="reg_item"][value="' + regSel + '"]').prop('checked', true); }
        if (conSel > 0) { $('input[name="con_item"][value="' + conSel + '"]').prop('checked', true); }
        hasReg = $('input[name="reg_item"]:checked').length > 0;
        hasCon = $('input[name="con_item"]:checked').length > 0;
        $('#btnApplyVisitFeeItems').prop('disabled', !(hasReg && hasCon));
    }, 'json').fail(function() {
        $('#visitFeeItemBody').html('<div class="alert alert-danger">Network error. Please try again.</div>');
    });
});

$(document).on('change', 'input[name="reg_item"], input[name="con_item"]', function() {
    var hasReg = $('input[name="reg_item"]:checked').length > 0;
    var hasCon = $('input[name="con_item"]:checked').length > 0;
    $('#btnApplyVisitFeeItems').prop('disabled', !(hasReg && hasCon));
});

$(document).on('click', '#btnApplyVisitFeeItems', function() {
    var regId = parseInt($('input[name="reg_item"]:checked').val(), 10) || 0;
    var conId = parseInt($('input[name="con_item"]:checked').val(), 10) || 0;
    if (regId <= 0 || conId <= 0) {
        $('#visitFeeItemMsg').html('<div class="alert alert-warning">Select both Registration and Consultation items.</div>');
        return;
    }

    var req = { registration_fee_item_id: regId, consultation_fee_item_id: conId };
    req[csrfName] = csrfHash;

    $('#btnApplyVisitFeeItems').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Applying...');
    $.post(VISIT_FEE_ITEM_APPLY_URL, req, function(res) {
        if (res && res.csrf_hash) { csrfHash = res.csrf_hash; }
        if (!res || !res.success) {
            $('#visitFeeItemMsg').html('<div class="alert alert-danger">' + _escapeHtml((res && res.error) ? res.error : 'Apply failed') + '</div>');
            $('#btnApplyVisitFeeItems').prop('disabled', false).html('<i class="fa fa-check"></i> Apply IDs');
            return;
        }

        $('#registration_fee_item_id').val(regId);
        $('#consultation_fee_item_id').val(conId);
        $('#visitFeeItemMsg').html('<div class="alert alert-success">' + _escapeHtml(res.message || 'Applied') + '</div>');
        $('#btnApplyVisitFeeItems').html('<i class="fa fa-check"></i> Apply IDs');
        setTimeout(function() { $('#visitFeeItemModal').modal('hide'); }, 700);
    }, 'json').fail(function() {
        $('#visitFeeItemMsg').html('<div class="alert alert-danger">Network error. Please try again.</div>');
        $('#btnApplyVisitFeeItems').prop('disabled', false).html('<i class="fa fa-check"></i> Apply IDs');
    });
});

$(document).on('click', '.btn-preview-billing', function() {
    var $btn = $(this);
    currentIopId     = $btn.data('iop');
    currentPatientNo = $btn.data('patient');
    var patientName  = $btn.data('name');

    $('#billingPreviewBody').html('<div class="text-center" style="padding:20px;"><i class="fa fa-spinner fa-spin fa-2x"></i><br>Loading fee preview...</div>');
    $('#btnConfirmBilling').prop('disabled', true);
    $('#billingPreviewModal').modal('show');

    var previewData = { iop_id: currentIopId, patient_no: currentPatientNo };
    previewData[csrfName] = csrfHash;
    $.post(BILLING_PREVIEW_URL, previewData, function(res) {
        if (!res.success) {
            $('#billingPreviewBody').html('<div class="alert alert-danger">' + (res.error || 'Error loading preview') + '</div>');
            return;
        }
        var d       = res.data;
        var fees    = d.fees;
        var visit   = d.visit_info;
        var payer   = d.payer || 'CASH';
        var total   = parseFloat(fees.total) || 0;

        var visitLabels = {
            'FIRST_VISIT': '<span class="label label-primary">First Visit</span>',
            'REVIEW': '<span class="label label-success">Review</span>',
            'FOLLOW_UP': '<span class="label label-info">Follow-Up</span>',
            'WALK_IN': '<span class="label label-default">Walk-In</span>',
            'MISSED_APPOINTMENT': '<span class="label label-warning">Missed Appointment</span>',
            'EMERGENCY': '<span class="label label-danger">Emergency</span>'
        };
        var vLabel = visitLabels[visit.visit_type] || ('<span class="label label-default">' + visit.visit_type + '</span>');

        var regRow = fees.apply_registration
            ? '<tr><td>Registration Fee</td><td style="text-align:right;color:#e74c3c;"><strong>GHS ' + parseFloat(fees.registration_fee).toFixed(2) + '</strong></td></tr>'
            : '<tr><td>Registration Fee</td><td style="text-align:right;color:#aaa;"><em>Not billed</em></td></tr>';

        var conRow;
        if (fees.consultation_waived) {
            conRow = '<tr><td>Consultation Fee</td><td style="text-align:right;">'
                + '<span style="text-decoration:line-through;color:#aaa;">GHS ' + parseFloat(fees.consultation_fee === 0 ? d.fees.consultation_fee : fees.consultation_fee).toFixed(2) + '</span> '
                + '<span class="label label-success">Waived</span>'
                + (fees.waiver_reason ? '<br><small class="text-muted">' + fees.waiver_reason + '</small>' : '')
                + '</td></tr>';
        } else {
            conRow = '<tr><td>Consultation Fee</td><td style="text-align:right;color:#e74c3c;"><strong>GHS ' + parseFloat(fees.consultation_fee).toFixed(2) + '</strong></td></tr>';
        }

        var html = '<div style="margin-bottom:12px;">'
            + '<strong>' + $('<div>').text(patientName).html() + '</strong> &nbsp;'
            + '<small class="text-muted">' + currentPatientNo + '</small>'
            + '</div>'
            + '<div style="margin-bottom:8px;">Visit Type: ' + vLabel + ' &nbsp; Payer: <span class="label label-' + (payer==='NHIS'?'blue':'default') + '">' + payer + '</span></div>'
            + (visit.consultation_waived && visit.waiver_reason ? '<div class="alert alert-success" style="padding:6px 10px;font-size:12px;"><i class="fa fa-check"></i> ' + $('<div>').text(visit.waiver_reason).html() + '</div>' : '')
            + '<div class="table-responsive">'
            + '<table class="table table-condensed" style="margin-top:10px;">'
            + '<thead><tr><th>Item</th><th style="text-align:right;">Amount</th></tr></thead>'
            + '<tbody>' + regRow + conRow + '</tbody>'
            + '<tfoot><tr><th>TOTAL</th><th style="text-align:right;font-size:16px;color:#27ae60;">GHS ' + total.toFixed(2) + '</th></tr></tfoot>'
            + '</table>'
            + '</div>';

        if (total === 0 && fees.consultation_waived && !fees.apply_registration) {
            html += '<div class="alert alert-info" style="padding:6px 10px;font-size:12px;"><i class="fa fa-info-circle"></i> This visit has no charges. Confirming will mark it as billed.</div>';
        }

        $('#billingPreviewBody').html(html);
        $('#btnConfirmBilling').prop('disabled', false);
    }, 'json').fail(function() {
        $('#billingPreviewBody').html('<div class="alert alert-danger">Network error. Please try again.</div>');
    });
});

$('#btnConfirmBilling').on('click', function() {
    if (!currentIopId || !currentPatientNo) return;
    var $btn = $(this);
    $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Processing...');

    var billingData = { iop_id: currentIopId, patient_no: currentPatientNo };
    billingData[csrfName] = csrfHash;
    $.post(ONE_CLICK_BILLING_URL, billingData, function(res) {
        if (res && res.success) {
            var fees  = res.fees;
            var total = parseFloat(fees.total) || 0;
            var msg   = '<div class="alert alert-success"><i class="fa fa-check-circle"></i> '
                + '<strong>Bill Generated!</strong> '
                + 'Visit: ' + res.visit_info.visit_type.replace(/_/g,' ') + ' | '
                + 'Total: <strong>GHS ' + total.toFixed(2) + '</strong>'
                + (fees.consultation_waived ? ' (Consultation Waived)' : '')
                + '</div>';
            $('#billingPreviewModal').modal('hide');
            $('section.content').prepend(msg);
            // Remove from pending table
            $('#pendingTable tr[data-iop="' + currentIopId + '"]').fadeOut(500, function() { $(this).remove(); });
            var pending = parseInt($('.info-box.bg-yellow .info-box-number').text()) - 1;
            $('.info-box.bg-yellow .info-box-number').text(Math.max(0, pending));
        } else {
            $('#billingPreviewBody').html('<div class="alert alert-danger">' + (res && res.error ? res.error : 'Billing failed. Please try again.') + '</div>');
            $btn.prop('disabled', false).html('<i class="fa fa-bolt"></i> Confirm &amp; Generate Bill');
        }
    }, 'json').fail(function() {
        $('#billingPreviewBody').html('<div class="alert alert-danger">Network error. Please try again.</div>');
        $btn.prop('disabled', false).html('<i class="fa fa-bolt"></i> Confirm &amp; Generate Bill');
    });
});
</script>

        </aside>
    </div>
    <script src="<?php echo base_url();?>public/js/jquery.min.js"></script>
    <script src="<?php echo base_url();?>public/js/bootstrap.min.js" type="text/javascript"></script>
    <script src="<?php echo base_url();?>public/js/AdminLTE/app.js" type="text/javascript"></script>
</body>
</html>
