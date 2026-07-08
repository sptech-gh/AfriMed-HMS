<?php if (!defined('BASEPATH')) exit('No direct script access allowed'); ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Add Service — Walk-In</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo base_url()?>public/css/font-awesome.min.css" rel="stylesheet">
    <link href="<?php echo base_url()?>public/css/AdminLTE.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo base_url()?>public/css/jQueryUI/jquery-ui-1.10.3.custom.min.css">
    <style>
        /* ── step bar ── */
        .step-indicator{display:flex;align-items:center;margin-bottom:24px;gap:0;}
        .step-indicator .step{flex:1;text-align:center;position:relative;}
        .step-indicator .step-circle{width:36px;height:36px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-weight:700;font-size:15px;border:2px solid #dee2e6;background:#fff;color:#adb5bd;}
        .step-indicator .step.active .step-circle{background:#1a6fa5;border-color:#1a6fa5;color:#fff;}
        .step-indicator .step.done .step-circle{background:#27a063;border-color:#27a063;color:#fff;}
        .step-indicator .step-line{flex:1;height:2px;background:#dee2e6;align-self:center;}
        .step-indicator .step-line.done{background:#27a063;}
        .step-indicator .step-label{font-size:11px;color:#6c757d;margin-top:4px;font-weight:600;}
        .step-indicator .step.active .step-label{color:#1a6fa5;}
        .step-indicator .step.done .step-label{color:#27a063;}

        /* ── client bar ── */
        .client-info-bar{background:#f0f7ff;border:1px solid #c7dff0;border-radius:8px;padding:12px 18px;margin-bottom:18px;display:flex;align-items:center;gap:16px;}
        .client-info-bar .avatar{width:40px;height:40px;border-radius:50%;background:#1a6fa5;color:#fff;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;}
        .client-info-bar .name{font-weight:700;color:#1a3a5c;font-size:15px;}
        .client-info-bar .meta{font-size:12px;color:#4a6278;}

        /* ── form card ── */
        .txn-form-card{max-width:700px;margin:0 auto;background:#fff;border-radius:10px;box-shadow:0 2px 16px rgba(0,0,0,.08);padding:28px 32px;}
        .txn-form-card label{font-weight:600;font-size:13px;color:#374151;}
        .txn-form-card .form-control{border-radius:6px;height:42px;font-size:14px;}
        .txn-form-card textarea.form-control{height:auto;}

        /* ── service tabs ── */
        .svc-tabs{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:16px;}
        .svc-tab-btn{border:2px solid #dee2e6;border-radius:8px;padding:8px 14px;cursor:pointer;background:#fafafa;font-size:12px;font-weight:600;color:#6c757d;transition:all .15s;display:flex;align-items:center;gap:6px;}
        .svc-tab-btn:hover{border-color:#1a6fa5;color:#1a6fa5;background:#e8f4fd;}
        .svc-tab-btn.active{border-color:#1a6fa5;background:#1a6fa5;color:#fff;}
        .svc-tab-btn .badge-count{background:rgba(255,255,255,.3);color:inherit;border-radius:10px;padding:1px 6px;font-size:10px;display:none;}
        .svc-tab-btn.has-items .badge-count{display:inline;}

        /* ── service panel ── */
        .svc-panel{display:none;background:#f8fbff;border:1px solid #dbe7f4;border-radius:8px;padding:14px 16px;margin-bottom:14px;}
        .svc-panel.active{display:block;}
        .svc-panel .panel-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;}
        .svc-panel .panel-head h5{margin:0;font-weight:700;color:#1a6fa5;font-size:14px;}
        .svc-panel table input{height:34px;font-size:13px;border-radius:6px;}
        .svc-panel .panel-total{font-size:15px;font-weight:800;text-align:right;color:#1a3a5c;margin-top:8px;}

        /* ── grand total ── */
        .grand-total-bar{background:#1a3a5c;color:#fff;border-radius:8px;padding:14px 20px;display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;}
        .grand-total-bar .gt-label{font-size:13px;font-weight:600;opacity:.85;}
        .grand-total-bar .gt-val{font-size:26px;font-weight:900;}

        /* ── buttons ── */
        .btn-pay{width:100%;height:50px;font-size:17px;font-weight:700;border-radius:6px;border:none;background:#27a063;color:#fff;}
        .btn-pay:hover{background:#1e8a53;}

        /* ── history table ── */
        .prev-txn-list{margin-top:0;}
        .badge-service{display:inline-block;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;}
        .svc-Laboratory{background:#dbeafe;color:#1e40af;}
        .svc-Sonography{background:#ede9fe;color:#6d28d9;}
        .svc-Pharmacy{background:#dcfce7;color:#166534;}
        .svc-Procedure{background:#fef3c7;color:#92400e;}
        .svc-Consultation{background:#e0f2fe;color:#0369a1;}
        .svc-Radiology{background:#fce7f3;color:#9d174d;}
        .svc-Other{background:#f3f4f6;color:#374151;}
        .status-Paid{color:#16a34a;font-weight:600;}
        .status-Pending{color:#d97706;font-weight:600;}
        .status-Cancelled{color:#dc2626;font-weight:600;}
        .ui-autocomplete{z-index:9999999 !important;}
    </style>
</head>
<body class="skin-blue">
<?php require_once(APPPATH.'views/include/header.php'); ?>

<div class="wrapper row-offcanvas row-offcanvas-left">
    <?php require_once(APPPATH.'views/include/sidebar.php'); ?>

    <aside class="right-side">
        <section class="content-header">
            <h1><i class="fa fa-plus-circle"></i> Add Service</h1>
            <ol class="breadcrumb">
                <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                <li><a href="<?php echo base_url()?>app/walkin">Walk-In</a></li>
                <li class="active">Add Service</li>
            </ol>
        </section>

        <section class="content">
            <?php if(isset($message)) echo $message; ?>

            <div class="row">
                <div class="col-md-7">
                    <div class="txn-form-card">

                        <!-- Step Indicator -->
                        <div class="step-indicator">
                            <div class="step done">
                                <div class="step-circle"><i class="fa fa-check"></i></div>
                                <div class="step-label">Register</div>
                            </div>
                            <div class="step-line done"></div>
                            <div class="step active">
                                <div class="step-circle">2</div>
                                <div class="step-label">Add Services</div>
                            </div>
                            <div class="step-line"></div>
                            <div class="step">
                                <div class="step-circle">3</div>
                                <div class="step-label">Payment</div>
                            </div>
                            <div class="step-line"></div>
                            <div class="step">
                                <div class="step-circle">4</div>
                                <div class="step-label">Receipt</div>
                            </div>
                        </div>

                        <!-- Client Info Bar -->
                        <div class="client-info-bar">
                            <div class="avatar"><i class="fa fa-user"></i></div>
                            <div>
                                <div class="name"><?php echo htmlspecialchars($client->client_name); ?></div>
                                <div class="meta">
                                    <?php if($client->phone): ?><i class="fa fa-phone"></i> <?php echo htmlspecialchars($client->phone); ?><?php endif; ?>
                                    <?php if($client->gender): ?> &nbsp;|&nbsp; <?php echo htmlspecialchars($client->gender); ?><?php endif; ?>
                                    <?php if($client->referral): ?> &nbsp;|&nbsp; Ref: <?php echo htmlspecialchars($client->referral); ?><?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <h4 style="margin:0 0 16px;color:#1a6fa5;font-weight:700;border-bottom:1px solid #e9ecef;padding-bottom:10px;">
                            <i class="fa fa-stethoscope"></i> Step 2 — Select Services &amp; Items
                            <small class="text-muted" style="font-size:12px;font-weight:400;"> Add items to one or more services below</small>
                        </h4>

                        <form method="post" action="<?php echo base_url()?>app/walkin/save_transaction" id="frmTransaction">
                            <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                            <input type="hidden" name="walkin_client_id" value="<?php echo (int)$client->id; ?>">
                            <input type="hidden" name="multi_service_mode" value="1">
                            <input type="hidden" name="cart_json" id="cartJsonInput" value="{}">

                            <!-- Service Type Tabs -->
                            <div class="form-group" style="margin-bottom:10px;">
                                <label style="margin-bottom:8px;">Service Types <small class="text-muted">(click to open / switch)</small></label>
                                <div class="svc-tabs" id="svcTabs">
                                    <div class="svc-tab-btn" data-service="Laboratory" id="tab-Laboratory" onclick="openTab('Laboratory')">
                                        <i class="fa fa-flask"></i> Laboratory <span class="badge-count" id="cnt-Laboratory">0</span>
                                    </div>
                                    <div class="svc-tab-btn" data-service="Sonography" id="tab-Sonography" onclick="openTab('Sonography')">
                                        <i class="fa fa-heartbeat"></i> Sonography <span class="badge-count" id="cnt-Sonography">0</span>
                                    </div>
                                    <div class="svc-tab-btn" data-service="Radiology" id="tab-Radiology" onclick="openTab('Radiology')">
                                        <i class="fa fa-stethoscope"></i> Radiology <span class="badge-count" id="cnt-Radiology">0</span>
                                    </div>
                                    <div class="svc-tab-btn" data-service="Pharmacy" id="tab-Pharmacy" onclick="openTab('Pharmacy')">
                                        <i class="fa fa-medkit"></i> Pharmacy <span class="badge-count" id="cnt-Pharmacy">0</span>
                                    </div>
                                    <div class="svc-tab-btn" data-service="Procedure" id="tab-Procedure" onclick="openTab('Procedure')">
                                        <i class="fa fa-scissors"></i> Procedure <span class="badge-count" id="cnt-Procedure">0</span>
                                    </div>
                                    <div class="svc-tab-btn" data-service="Consultation" id="tab-Consultation" onclick="openTab('Consultation')">
                                        <i class="fa fa-user-md"></i> Consultation <span class="badge-count" id="cnt-Consultation">0</span>
                                    </div>
                                    <div class="svc-tab-btn" data-service="Other" id="tab-Other" onclick="openTab('Other')">
                                        <i class="fa fa-ellipsis-h"></i> Other <span class="badge-count" id="cnt-Other">0</span>
                                    </div>
                                </div>
                            </div>

                            <!-- ══ Service Panels (one per service type, shown/hidden) ══ -->

                            <!-- Laboratory Panel -->
                            <div class="svc-panel" id="panel-Laboratory">
                                <div class="panel-head">
                                    <h5><i class="fa fa-flask"></i> Laboratory Items</h5>
                                    <button type="button" class="btn btn-xs btn-primary" onclick="addSvcRow('Laboratory')"><i class="fa fa-plus"></i> Add Item</button>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-condensed" style="margin:0;">
                                        <thead><tr style="background:#f0f7ff;"><th style="width:50%;">Item</th><th style="width:13%;">Qty</th><th style="width:16%;">Unit (GHS)</th><th style="width:16%;">Line (GHS)</th><th style="width:5%;"></th></tr></thead>
                                        <tbody id="body-Laboratory"></tbody>
                                    </table>
                                </div>
                                <div class="panel-total">Subtotal: GHS <span id="total-Laboratory">0.00</span></div>
                            </div>

                            <!-- Sonography Panel -->
                            <div class="svc-panel" id="panel-Sonography">
                                <div class="panel-head">
                                    <h5><i class="fa fa-heartbeat"></i> Sonography Items</h5>
                                    <button type="button" class="btn btn-xs btn-primary" onclick="addSvcRow('Sonography')"><i class="fa fa-plus"></i> Add Item</button>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-condensed" style="margin:0;">
                                        <thead><tr style="background:#f0f7ff;"><th style="width:50%;">Item</th><th style="width:13%;">Qty</th><th style="width:16%;">Unit (GHS)</th><th style="width:16%;">Line (GHS)</th><th style="width:5%;"></th></tr></thead>
                                        <tbody id="body-Sonography"></tbody>
                                    </table>
                                </div>
                                <div class="panel-total">Subtotal: GHS <span id="total-Sonography">0.00</span></div>
                            </div>

                            <!-- Radiology Panel -->
                            <div class="svc-panel" id="panel-Radiology">
                                <div class="panel-head">
                                    <h5><i class="fa fa-stethoscope"></i> Radiology Items</h5>
                                    <button type="button" class="btn btn-xs btn-primary" onclick="addSvcRow('Radiology')"><i class="fa fa-plus"></i> Add Item</button>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-condensed" style="margin:0;">
                                        <thead><tr style="background:#f0f7ff;"><th style="width:50%;">Item</th><th style="width:13%;">Qty</th><th style="width:16%;">Unit (GHS)</th><th style="width:16%;">Line (GHS)</th><th style="width:5%;"></th></tr></thead>
                                        <tbody id="body-Radiology"></tbody>
                                    </table>
                                </div>
                                <div class="panel-total">Subtotal: GHS <span id="total-Radiology">0.00</span></div>
                            </div>

                            <!-- Pharmacy Panel -->
                            <div class="svc-panel" id="panel-Pharmacy">
                                <div class="panel-head">
                                    <h5><i class="fa fa-medkit"></i> Pharmacy Items</h5>
                                    <button type="button" class="btn btn-xs btn-primary" onclick="addPharmRow()"><i class="fa fa-plus"></i> Add Drug</button>
                                </div>
                                <small class="text-muted" style="display:block;margin-bottom:8px;">Stock deducted when marked Paid. NHIS not available for Pharmacy.</small>
                                <div class="table-responsive">
                                    <table class="table table-condensed" style="margin:0;">
                                        <thead><tr style="background:#f0f7ff;"><th style="width:50%;">Drug</th><th style="width:13%;">Qty</th><th style="width:16%;">Unit (GHS)</th><th style="width:16%;">Line (GHS)</th><th style="width:5%;"></th></tr></thead>
                                        <tbody id="body-Pharmacy"></tbody>
                                    </table>
                                </div>
                                <div class="panel-total">Subtotal: GHS <span id="total-Pharmacy">0.00</span></div>
                            </div>

                            <!-- Procedure Panel -->
                            <div class="svc-panel" id="panel-Procedure">
                                <div class="panel-head">
                                    <h5><i class="fa fa-scissors"></i> Procedure Items</h5>
                                    <button type="button" class="btn btn-xs btn-primary" onclick="addSvcRow('Procedure')"><i class="fa fa-plus"></i> Add Item</button>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-condensed" style="margin:0;">
                                        <thead><tr style="background:#f0f7ff;"><th style="width:50%;">Item</th><th style="width:13%;">Qty</th><th style="width:16%;">Unit (GHS)</th><th style="width:16%;">Line (GHS)</th><th style="width:5%;"></th></tr></thead>
                                        <tbody id="body-Procedure"></tbody>
                                    </table>
                                </div>
                                <div class="panel-total">Subtotal: GHS <span id="total-Procedure">0.00</span></div>
                            </div>

                            <!-- Consultation Panel -->
                            <div class="svc-panel" id="panel-Consultation">
                                <div class="panel-head">
                                    <h5><i class="fa fa-user-md"></i> Consultation Items</h5>
                                    <button type="button" class="btn btn-xs btn-primary" onclick="addSvcRow('Consultation')"><i class="fa fa-plus"></i> Add Item</button>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-condensed" style="margin:0;">
                                        <thead><tr style="background:#f0f7ff;"><th style="width:50%;">Item</th><th style="width:13%;">Qty</th><th style="width:16%;">Unit (GHS)</th><th style="width:16%;">Line (GHS)</th><th style="width:5%;"></th></tr></thead>
                                        <tbody id="body-Consultation"></tbody>
                                    </table>
                                </div>
                                <div class="panel-total">Subtotal: GHS <span id="total-Consultation">0.00</span></div>
                            </div>

                            <!-- Other Panel -->
                            <div class="svc-panel" id="panel-Other">
                                <div class="panel-head">
                                    <h5><i class="fa fa-ellipsis-h"></i> Other Items</h5>
                                    <button type="button" class="btn btn-xs btn-primary" onclick="addSvcRow('Other')"><i class="fa fa-plus"></i> Add Item</button>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-condensed" style="margin:0;">
                                        <thead><tr style="background:#f0f7ff;"><th style="width:50%;">Item</th><th style="width:13%;">Qty</th><th style="width:16%;">Unit (GHS)</th><th style="width:16%;">Line (GHS)</th><th style="width:5%;"></th></tr></thead>
                                        <tbody id="body-Other"></tbody>
                                    </table>
                                </div>
                                <div class="panel-total">Subtotal: GHS <span id="total-Other">0.00</span></div>
                            </div>

                            <!-- Grand Total Bar -->
                            <div class="grand-total-bar" id="grandTotalBar" style="display:none;">
                                <span class="gt-label"><i class="fa fa-calculator"></i> Grand Total (All Services)</span>
                                <span class="gt-val">GHS <span id="grandTotal">0.00</span></span>
                            </div>

                            <!-- Payment Options -->
                            <div class="row">
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label for="payment_method">Payment Method</label>
                                        <select name="payment_method" id="payment_method" class="form-control">
                                            <option value="Cash" selected>Cash</option>
                                            <option value="MoMo">Mobile Money (MoMo)</option>
                                            <option value="Card">Card</option>
                                            <option value="NHIS" id="optNhis">NHIS</option>
                                            <option value="Cheque">Cheque</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label for="payment_status">Payment Status</label>
                                        <select name="payment_status" id="payment_status" class="form-control">
                                            <option value="Paid" selected>Paid</option>
                                            <option value="Pending">Pending</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="notes">Notes <small class="text-muted">(optional)</small></label>
                                <input type="text" name="notes" id="notes" class="form-control" placeholder="Any additional notes...">
                            </div>

                            <button type="submit" class="btn btn-pay" id="btnPay">
                                <i class="fa fa-check-circle"></i> Receive Payment &amp; Generate Receipt
                            </button>
                        </form>

                        <div style="text-align:center;margin-top:14px;">
                            <a href="<?php echo base_url()?>app/walkin" class="text-muted" style="font-size:13px;">
                                <i class="fa fa-arrow-left"></i> Back to Dashboard
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Previous Transactions for this client -->
                <div class="col-md-5">
                    <div class="box box-info">
                        <div class="box-header with-border">
                            <h3 class="box-title"><i class="fa fa-history"></i> Previous Services — <?php echo htmlspecialchars($client->client_name); ?></h3>
                        </div>
                        <div class="box-body no-padding">
                            <?php if (empty($transactions)): ?>
                                <p class="text-muted text-center" style="padding:20px 0;">No previous services today.</p>
                            <?php else: ?>
                            <table class="table table-condensed prev-txn-list">
                                <thead><tr><th>Service</th><th>Description</th><th>GHS</th><th>Status</th><th></th></tr></thead>
                                <tbody>
                                <?php foreach ($transactions as $tx): ?>
                                <tr>
                                    <td><span class="badge-service svc-<?php echo $tx->service_type; ?>"><?php echo $tx->service_type; ?></span></td>
                                    <td style="max-width:120px;"><small><?php echo htmlspecialchars($tx->description); ?></small></td>
                                    <td><strong><?php echo number_format((float)$tx->amount,2); ?></strong></td>
                                    <td><span class="status-<?php echo $tx->payment_status; ?>"><?php echo $tx->payment_status; ?></span></td>
                                    <td><a href="<?php echo base_url()?>app/walkin/receipt/<?php echo $tx->id; ?>" class="btn btn-xs btn-default" title="View Receipt"><i class="fa fa-file-text-o"></i></a></td>
                                </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </aside>
</div>

<script src="<?php echo base_url()?>public/js/jquery.min.js"></script>
<script src="<?php echo base_url()?>public/js/jquery-ui-1.10.3.min.js"></script>
<script src="<?php echo base_url()?>public/js/bootstrap.min.js"></script>
<script src="<?php echo base_url()?>public/js/AdminLTE/app.js"></script>
<script>
var WALKIN_BASE_URL = <?php echo json_encode(base_url()); ?>;

/* ═══════════════════════════════════════════════════════
 * CART STATE
 * Each service type stores an array of item objects.
 * svc types: { item_id, label, qty, unit_price }
 * pharmacy:  { drug_id, label, qty, unit_price }
 * ═══════════════════════════════════════════════════════ */
var SVC_TYPES = ['Laboratory','Sonography','Radiology','Procedure','Consultation','Other'];
var currentTab = null;

/* ══════════════════════
 * UTILITIES
 * ══════════════════════ */
function fmt(n){ return parseFloat(n||0).toFixed(2); }

function searchUrl(svcType) {
    if (svcType === 'Laboratory')   return WALKIN_BASE_URL + 'app/walkin/search_lab_tests_json';
    if (svcType === 'Procedure')    return WALKIN_BASE_URL + 'app/walkin/search_procedures_json';
    if (svcType === 'Sonography')   return WALKIN_BASE_URL + 'app/walkin/search_sonography_tests_json';
    if (svcType === 'Radiology')    return WALKIN_BASE_URL + 'app/walkin/search_radiology_tests_json';
    if (svcType === 'Consultation') return WALKIN_BASE_URL + 'app/walkin/search_consultation_types_json';
    return WALKIN_BASE_URL + 'app/walkin/search_bill_particulars_json';
}

/* ══════════════════════
 * RECALCULATE TOTALS
 * ══════════════════════ */
function recalcPanel(svcType) {
    var total = 0;
    var bodyId = (svcType === 'Pharmacy') ? 'body-Pharmacy' : 'body-' + svcType;
    var qtyClass = (svcType === 'Pharmacy') ? '.pharm-qty' : '.svc-qty';
    var unitClass = (svcType === 'Pharmacy') ? '.pharm-unit-price' : '.svc-unit-price';
    var lineClass = (svcType === 'Pharmacy') ? '.pharm-line-total' : '.svc-line-total';

    $('#' + bodyId + ' tr').each(function(){
        var qty = parseFloat($(this).find(qtyClass).val() || 0);
        var price = parseFloat($(this).find(unitClass).val() || 0);
        var line = qty * price;
        if (!isNaN(line)) total += line;
        $(this).find(lineClass).val(fmt(line));
    });
    $('#total-' + svcType).text(fmt(total));
    updateTabCount(svcType);
    recalcGrandTotal();
}

function recalcGrandTotal() {
    var grand = 0;
    var allTypes = SVC_TYPES.concat(['Pharmacy']);
    allTypes.forEach(function(st) {
        grand += parseFloat($('#total-' + st).text() || 0);
    });
    $('#grandTotal').text(fmt(grand));
    $('#grandTotalBar').toggle(grand > 0);
}

/* ══════════════════════
 * TAB COUNT BADGES
 * ══════════════════════ */
function updateTabCount(svcType) {
    var bodyId = 'body-' + svcType;
    var count = $('#' + bodyId + ' tr').length;
    var tab = $('#tab-' + svcType);
    $('#cnt-' + svcType).text(count);
    if (count > 0) {
        tab.addClass('has-items');
    } else {
        tab.removeClass('has-items');
    }
}

/* ══════════════════════
 * OPEN TAB
 * ══════════════════════ */
function openTab(svcType) {
    // Hide all panels, deactivate all tabs
    $('.svc-panel').removeClass('active');
    $('.svc-tab-btn').removeClass('active');

    // Show selected panel + activate tab
    $('#panel-' + svcType).addClass('active');
    $('#tab-' + svcType).addClass('active');
    currentTab = svcType;

    // If Pharmacy, disable NHIS
    if (svcType === 'Pharmacy') {
        if ($('#payment_method').val() === 'NHIS') {
            $('#payment_method').val('Cash');
        }
        $('#optNhis').prop('disabled', true).hide();
    } else {
        $('#optNhis').prop('disabled', false).show();
    }

    // Add first row automatically if panel is empty
    if ($('#body-' + svcType + ' tr').length === 0) {
        if (svcType === 'Pharmacy') {
            addPharmRow();
        } else {
            addSvcRow(svcType);
        }
    }
}

/* ══════════════════════
 * ADD SERVICE ITEM ROW
 * ══════════════════════ */
function addSvcRow(svcType, prefill) {
    prefill = prefill || {};
    var row = $('<tr>'+
        '<td>'+
            '<input type="text" class="form-control svc-item-search" placeholder="Search item..." autocomplete="off">'+
            '<input type="hidden" class="svc-item-id" value="">'+
        '</td>'+
        '<td><input type="text" class="form-control svc-qty" inputmode="decimal" value="1"></td>'+
        '<td><input type="text" class="form-control svc-unit-price" readonly value="0.00"></td>'+
        '<td><input type="text" class="form-control svc-line-total" readonly value="0.00"></td>'+
        '<td><button type="button" class="btn btn-xs btn-danger btnRemoveSvc"><i class="fa fa-times"></i></button></td>'+
    '</tr>');

    row.find('.btnRemoveSvc').on('click', function(){
        $(this).closest('tr').remove();
        recalcPanel(svcType);
    });
    row.find('.svc-qty').on('input', function(){
        var v = $(this).val().replace(/[^0-9.]/g,'');
        var p = v.split('.'); if (p.length>2) v = p[0]+'.'+p.slice(1).join('');
        if ($(this).val() !== v) $(this).val(v);
        recalcPanel(svcType);
    });
    row.find('.svc-item-search').autocomplete({
        source: function(req, resp) {
            $.getJSON(searchUrl(svcType), { q: req.term, pm: $('#payment_method').val() }, function(data){
                resp($.map(data, function(it){
                    return { label: it.label + ' | GHS ' + fmt(it.unit_price), value: it.value, item_id: it.item_id, unit_price: it.unit_price };
                }));
            });
        },
        minLength: 2,
        select: function(event, ui) {
            row.find('.svc-item-id').val(ui.item.item_id);
            row.find('.svc-unit-price').val(fmt(ui.item.unit_price));
            recalcPanel(svcType);
        }
    });

    if (prefill.label)      row.find('.svc-item-search').val(prefill.label);
    if (prefill.item_id)    row.find('.svc-item-id').val(prefill.item_id);
    if (prefill.qty)        row.find('.svc-qty').val(prefill.qty);
    if (prefill.unit_price) row.find('.svc-unit-price').val(fmt(prefill.unit_price));

    $('#body-' + svcType).append(row);
    recalcPanel(svcType);
}

/* ══════════════════════
 * ADD PHARMACY ROW
 * ══════════════════════ */
function addPharmRow(prefill) {
    prefill = prefill || {};
    var row = $('<tr>'+
        '<td>'+
            '<input type="text" class="form-control pharm-drug-search" placeholder="Search drug..." autocomplete="off">'+
            '<input type="hidden" class="pharm-drug-id" value="">'+
        '</td>'+
        '<td><input type="text" class="form-control pharm-qty" inputmode="decimal" value="1"></td>'+
        '<td><input type="text" class="form-control pharm-unit-price" value="0.00"></td>'+
        '<td><input type="text" class="form-control pharm-line-total" readonly value="0.00"></td>'+
        '<td><button type="button" class="btn btn-xs btn-danger btnRemovePharm"><i class="fa fa-times"></i></button></td>'+
    '</tr>');

    row.find('.btnRemovePharm').on('click', function(){
        $(this).closest('tr').remove();
        recalcPanel('Pharmacy');
    });
    row.find('.pharm-qty, .pharm-unit-price').on('input', function(){
        var v = $(this).val().replace(/[^0-9.]/g,'');
        var p = v.split('.'); if (p.length>2) v = p[0]+'.'+p.slice(1).join('');
        if ($(this).val() !== v) $(this).val(v);
        recalcPanel('Pharmacy');
    });
    row.find('.pharm-drug-search').autocomplete({
        source: function(req, resp) {
            $.getJSON(WALKIN_BASE_URL + 'app/walkin/search_drugs_json', { q: req.term }, function(data){
                resp($.map(data, function(it){
                    return { label: it.label + ' | Stock: ' + it.nStock + ' | GHS ' + it.nPrice, value: it.value, drug_id: it.drug_id, nStock: it.nStock, nPrice: it.nPrice };
                }));
            });
        },
        minLength: 2,
        select: function(event, ui) {
            row.find('.pharm-drug-id').val(ui.item.drug_id);
            if (parseFloat(row.find('.pharm-unit-price').val()||0) <= 0) {
                row.find('.pharm-unit-price').val(fmt(ui.item.nPrice));
            }
            recalcPanel('Pharmacy');
        }
    });

    if (prefill.label)      row.find('.pharm-drug-search').val(prefill.label);
    if (prefill.drug_id)    row.find('.pharm-drug-id').val(prefill.drug_id);
    if (prefill.qty)        row.find('.pharm-qty').val(prefill.qty);
    if (prefill.unit_price) row.find('.pharm-unit-price').val(fmt(prefill.unit_price));

    $('#body-Pharmacy').append(row);
    recalcPanel('Pharmacy');
}

/* ══════════════════════
 * BUILD CART JSON
 * Collects all rows from all panels into a
 * single object keyed by service type.
 * ══════════════════════ */
function buildCartJson() {
    var cart = {};

    // Non-pharmacy service types
    SVC_TYPES.forEach(function(st) {
        var rows = [];
        $('#body-' + st + ' tr').each(function(){
            var item_id = $(this).find('.svc-item-id').val();
            var qty = parseFloat($(this).find('.svc-qty').val() || 0);
            var unit_price = parseFloat($(this).find('.svc-unit-price').val() || 0);
            var label = $(this).find('.svc-item-search').val() || '';
            if (item_id && parseInt(item_id,10) > 0 && qty > 0) {
                rows.push({ item_id: parseInt(item_id,10), label: label, qty: qty, unit_price: unit_price });
            }
        });
        if (rows.length > 0) cart[st] = rows;
    });

    // Pharmacy
    var pharmRows = [];
    $('#body-Pharmacy tr').each(function(){
        var drug_id = $(this).find('.pharm-drug-id').val();
        var qty = parseFloat($(this).find('.pharm-qty').val() || 0);
        var unit_price = parseFloat($(this).find('.pharm-unit-price').val() || 0);
        var label = $(this).find('.pharm-drug-search').val() || '';
        if (drug_id && parseInt(drug_id,10) > 0 && qty > 0) {
            pharmRows.push({ drug_id: parseInt(drug_id,10), label: label, qty: qty, unit_price: unit_price });
        }
    });
    if (pharmRows.length > 0) cart['Pharmacy'] = pharmRows;

    return cart;
}

/* ══════════════════════
 * FORM SUBMIT
 * ══════════════════════ */
$('#frmTransaction').on('submit', function(e){
    var cart = buildCartJson();
    var serviceCount = Object.keys(cart).length;

    if (serviceCount === 0) {
        e.preventDefault();
        alert('Please add at least one item to any service.');
        return false;
    }

    // Validate: every row must have a valid item/drug and qty
    var errors = [];
    $.each(cart, function(st, rows){
        rows.forEach(function(r, i){
            if (st === 'Pharmacy') {
                if (!r.drug_id || r.drug_id <= 0) errors.push(st + ' row ' + (i+1) + ': drug not selected.');
            } else {
                if (!r.item_id || r.item_id <= 0) errors.push(st + ' row ' + (i+1) + ': item not selected.');
            }
            if (r.qty <= 0) errors.push(st + ' row ' + (i+1) + ': quantity must be > 0.');
        });
    });
    if (errors.length > 0) {
        e.preventDefault();
        alert('Please fix the following:\n\n' + errors.join('\n'));
        return false;
    }

    // If pharmacy is in cart and payment_method is NHIS, block
    if (cart['Pharmacy'] && $('#payment_method').val() === 'NHIS') {
        e.preventDefault();
        alert('NHIS is not allowed for Pharmacy items. Please change the payment method or remove Pharmacy items.');
        return false;
    }

    $('#cartJsonInput').val(JSON.stringify(cart));
    $('#btnPay').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Processing...');
    return true;
});

/* ══════════════════════
 * INIT — open Laboratory tab by default
 * ══════════════════════ */
$(document).ready(function(){
    openTab('Laboratory');
});
</script>
</body>
</html>
