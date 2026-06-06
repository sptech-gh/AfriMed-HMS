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
        .step-indicator{display:flex;align-items:center;margin-bottom:28px;gap:0;}
        .step-indicator .step{flex:1;text-align:center;position:relative;}
        .step-indicator .step-circle{width:36px;height:36px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-weight:700;font-size:15px;border:2px solid #dee2e6;background:#fff;color:#adb5bd;}
        .step-indicator .step.active .step-circle{background:#1a6fa5;border-color:#1a6fa5;color:#fff;}
        .step-indicator .step.done .step-circle{background:#27a063;border-color:#27a063;color:#fff;}
        .step-indicator .step-line{flex:1;height:2px;background:#dee2e6;align-self:center;}
        .step-indicator .step-line.done{background:#27a063;}
        .step-indicator .step-label{font-size:11px;color:#6c757d;margin-top:4px;font-weight:600;}
        .step-indicator .step.active .step-label{color:#1a6fa5;}
        .step-indicator .step.done .step-label{color:#27a063;}
        .client-info-bar{background:#f0f7ff;border:1px solid #c7dff0;border-radius:8px;padding:12px 18px;margin-bottom:20px;display:flex;align-items:center;gap:16px;}
        .client-info-bar .avatar{width:40px;height:40px;border-radius:50%;background:#1a6fa5;color:#fff;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;}
        .client-info-bar .name{font-weight:700;color:#1a3a5c;font-size:15px;}
        .client-info-bar .meta{font-size:12px;color:#4a6278;}
        .txn-form-card{max-width:620px;margin:0 auto;background:#fff;border-radius:10px;box-shadow:0 2px 16px rgba(0,0,0,.08);padding:32px 36px;}
        .txn-form-card .form-control{border-radius:6px;height:42px;font-size:14px;}
        .txn-form-card textarea.form-control{height:auto;}
        .txn-form-card select.form-control{height:42px;}
        .txn-form-card label{font-weight:600;font-size:13px;color:#374151;}
        .amount-input{font-size:22px;font-weight:700;height:54px!important;text-align:right;}
        .service-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:6px;}
        .service-btn{border:2px solid #dee2e6;border-radius:8px;padding:10px 6px;text-align:center;cursor:pointer;transition:all .15s;background:#fafafa;}
        .service-btn:hover,.service-btn.selected{border-color:#1a6fa5;background:#e8f4fd;color:#1a6fa5;}
        .service-btn i{font-size:20px;display:block;margin-bottom:4px;}
        .service-btn span{font-size:11px;font-weight:600;}
        .btn-pay{width:100%;height:50px;font-size:17px;font-weight:700;border-radius:6px;border:none;background:#27a063;color:#fff;}
        .btn-pay:hover{background:#1e8a53;}
        .prev-txn-list{margin-top:0;}
        .badge-service{display:inline-block;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;}
        .svc-Laboratory{background:#dbeafe;color:#1e40af;}
        .svc-Sonography{background:#ede9fe;color:#6d28d9;}
        .svc-Pharmacy{background:#dcfce7;color:#166534;}
        .svc-Procedure{background:#fef3c7;color:#92400e;}
        .svc-Consultation{background:#e0f2fe;color:#0369a1;}
        .svc-Other{background:#f3f4f6;color:#374151;}
        .status-Paid{color:#16a34a;font-weight:600;}
        .status-Pending{color:#d97706;font-weight:600;}
        .status-Cancelled{color:#dc2626;font-weight:600;}
        .pharm-box{background:#f8fbff;border:1px solid #dbe7f4;border-radius:8px;padding:12px 14px;margin-bottom:16px;display:none;}
        .pharm-box .pharm-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;}
        .pharm-box .pharm-head h5{margin:0;font-weight:700;color:#1a6fa5;}
        .pharm-total{font-size:18px;font-weight:800;text-align:right;color:#1a3a5c;}
        .pharm-table input{height:34px;font-size:13px;border-radius:6px;}
        .svc-box{background:#f8fbff;border:1px solid #dbe7f4;border-radius:8px;padding:12px 14px;margin-bottom:16px;display:none;}
        .svc-box .svc-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;}
        .svc-box .svc-head h5{margin:0;font-weight:700;color:#1a6fa5;}
        .svc-total{font-size:18px;font-weight:800;text-align:right;color:#1a3a5c;}
        .svc-table input{height:34px;font-size:13px;border-radius:6px;}
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
                                <div class="step-label">Add Service</div>
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

                        <h4 style="margin:0 0 18px;color:#1a6fa5;font-weight:700;border-bottom:1px solid #e9ecef;padding-bottom:10px;">
                            <i class="fa fa-stethoscope"></i> Step 2 — Service Details
                        </h4>

                        <form method="post" action="<?php echo base_url()?>app/walkin/save_transaction" id="frmTransaction">
                            <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                            <input type="hidden" name="walkin_client_id" value="<?php echo (int)$client->id; ?>">
                            <input type="hidden" name="service_type" id="service_type_hidden" value="Other">

                            <!-- Service Type Grid -->
                            <div class="form-group">
                                <label>Service Type <span class="text-danger">*</span></label>
                                <div class="service-grid">
                                    <div class="service-btn" data-service="Laboratory" onclick="selectService(this,'Laboratory')">
                                        <i class="fa fa-flask"></i><span>Laboratory</span>
                                    </div>
                                    <div class="service-btn" data-service="Sonography" onclick="selectService(this,'Sonography')">
                                        <i class="fa fa-heartbeat"></i><span>Sonography</span>
                                    </div>
                                    <div class="service-btn" data-service="Radiology" onclick="selectService(this,'Radiology')">
                                        <i class="fa fa-stethoscope"></i><span>Radiology</span>
                                    </div>
                                    <div class="service-btn" data-service="Pharmacy" onclick="selectService(this,'Pharmacy')">
                                        <i class="fa fa-medkit"></i><span>Pharmacy</span>
                                    </div>
                                    <div class="service-btn" data-service="Procedure" onclick="selectService(this,'Procedure')">
                                        <i class="fa fa-scissors"></i><span>Procedure</span>
                                    </div>
                                    <div class="service-btn" data-service="Consultation" onclick="selectService(this,'Consultation')">
                                        <i class="fa fa-user-md"></i><span>Consultation</span>
                                    </div>
                                    <div class="service-btn" data-service="Other" onclick="selectService(this,'Other')">
                                        <i class="fa fa-ellipsis-h"></i><span>Other</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Description -->
                            <div class="form-group">
                                <label for="description">Description</label>
                                <textarea name="description" id="description" class="form-control" rows="2"
                                    placeholder="e.g. Full Blood Count, Abdominal Scan, Dressing, Paracetamol 500mg x 20..."></textarea>
                                <span class="help-block" style="font-size:11px;">Be specific — this appears on the receipt and audit trail.</span>
                            </div>

                            <!-- Service Items (catalog-backed, quantity-only) -->
                            <div class="svc-box" id="svcBox">
                                <div class="svc-head">
                                    <h5><i class="fa fa-list"></i> Service Items</h5>
                                    <button type="button" class="btn btn-xs btn-primary" id="btnAddSvcItem"><i class="fa fa-plus"></i> Add Item</button>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-condensed svc-table" style="margin:0;">
                                        <thead>
                                            <tr style="background:#f0f7ff;">
                                                <th style="width:52%;">Item</th>
                                                <th style="width:14%;">Qty</th>
                                                <th style="width:16%;">Unit (GHS)</th>
                                                <th style="width:16%;">Line (GHS)</th>
                                                <th style="width:2%;"></th>
                                            </tr>
                                        </thead>
                                        <tbody id="svcItemsBody"></tbody>
                                    </table>
                                </div>
                                <div class="row" style="margin-top:10px;">
                                    <div class="col-xs-6" style="padding-top:6px;">
                                        <small class="text-muted">Select items from catalog; enter quantity manually. Prices are locked.</small>
                                    </div>
                                    <div class="col-xs-6">
                                        <div class="svc-total">Total: GHS <span id="svcTotal">0.00</span></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Pharmacy Cart (itemized) -->
                            <div class="pharm-box" id="pharmBox">
                                <div class="pharm-head">
                                    <h5><i class="fa fa-medkit"></i> Pharmacy Items</h5>
                                    <button type="button" class="btn btn-xs btn-primary" id="btnAddPharmItem"><i class="fa fa-plus"></i> Add Item</button>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-condensed pharm-table" style="margin:0;">
                                        <thead>
                                            <tr style="background:#f0f7ff;">
                                                <th style="width:52%;">Drug</th>
                                                <th style="width:14%;">Qty</th>
                                                <th style="width:16%;">Unit (GHS)</th>
                                                <th style="width:16%;">Line (GHS)</th>
                                                <th style="width:2%;"></th>
                                            </tr>
                                        </thead>
                                        <tbody id="pharmItemsBody"></tbody>
                                    </table>
                                </div>
                                <div class="row" style="margin-top:10px;">
                                    <div class="col-xs-6" style="padding-top:6px;">
                                        <small class="text-muted">Select drugs from catalog; enter quantity manually. Prices are locked. Stock deducted when Paid.</small>
                                    </div>
                                    <div class="col-xs-6">
                                        <div class="pharm-total">Total: GHS <span id="pharmTotal">0.00</span></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Amount -->
                            <div class="form-group">
                                <label for="amount">Amount (GHS) <span class="text-danger">*</span></label>
                                <input type="number" name="amount" id="amount" class="form-control amount-input"
                                    placeholder="0.00" step="0.01" min="0.01" readonly>
                            </div>

                            <div class="row">
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label for="payment_method">Payment Method</label>
                                        <select name="payment_method" id="payment_method" class="form-control">
                                            <option value="Cash" selected>Cash</option>
                                            <option value="NHIS" id="optNhis">NHIS</option>
                                            <option value="MoMo">Mobile Money (MoMo)</option>
                                            <option value="Card">Card</option>
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

<script src="<?php echo base_url()?>public/js/jquery-ui-1.10.3.min.js"></script>
<script src="<?php echo base_url()?>public/js/bootstrap.min.js"></script>
<script src="<?php echo base_url()?>public/js/AdminLTE/app.js"></script>
<script>
var WALKIN_BASE_URL = <?php echo json_encode(base_url()); ?>;

var walkinCartStore = {};
var walkinCurrentServiceType = '';

function formatMoney(n) {
    n = parseFloat(n || 0);
    return n.toFixed(2);
}

function walkinGetSvcRows() {
    var rows = [];
    $('#svcItemsBody tr').each(function(){
        var $tr = $(this);
        rows.push({
            label: $tr.find('.svc-item-search').val() || '',
            item_id: $tr.find('.svc-item-id').val() || '',
            qty: $tr.find('.svc-qty').val() || '1',
            unit_price: $tr.find('.svc-unit-price').val() || '0.00'
        });
    });
    return rows;
}

function walkinGetPharmRows() {
    var rows = [];
    $('#pharmItemsBody tr').each(function(){
        var $tr = $(this);
        rows.push({
            label: $tr.find('.pharm-drug-search').val() || '',
            drug_id: $tr.find('.pharm-drug-id').val() || '',
            qty: $tr.find('.pharm-qty').val() || '1',
            unit_price: $tr.find('.pharm-unit-price').val() || '0.00'
        });
    });
    return rows;
}

function walkinSaveCurrentCart(serviceType) {
    if (!serviceType) return;
    walkinCartStore[serviceType] = {
        svc: walkinGetSvcRows(),
        pharm: walkinGetPharmRows()
    };
}

function recalcSvcTotals() {
    var total = 0;
    $('#svcItemsBody tr').each(function(){
        var qty = parseFloat($(this).find('.svc-qty').val() || 0);
        var price = parseFloat($(this).find('.svc-unit-price').val() || 0);
        var line = qty * price;
        if (!isNaN(line)) {
            total += line;
        }
        $(this).find('.svc-line-total').val(formatMoney(line));
    });
    $('#svcTotal').text(formatMoney(total));
    $('#amount').val(formatMoney(total));
}

function svcSearchUrl(serviceType) {
    if (serviceType === 'Laboratory') return WALKIN_BASE_URL + 'app/walkin/search_lab_tests_json';
    if (serviceType === 'Procedure') return WALKIN_BASE_URL + 'app/walkin/search_procedures_json';
    if (serviceType === 'Sonography') return WALKIN_BASE_URL + 'app/walkin/search_sonography_tests_json';
    if (serviceType === 'Radiology') return WALKIN_BASE_URL + 'app/walkin/search_radiology_tests_json';
    if (serviceType === 'Consultation') return WALKIN_BASE_URL + 'app/walkin/search_consultation_types_json';
    return WALKIN_BASE_URL + 'app/walkin/search_bill_particulars_json';
}

function addSvcRow(prefill) {
    prefill = prefill || {};
    var row = $('<tr>'+
        '<td>'+
            '<input type="text" class="form-control svc-item-search" placeholder="Search item..." autocomplete="off">' +
            '<input type="hidden" name="service_item_id[]" class="svc-item-id" value="">' +
        '</td>'+
        '<td><input type="text" name="service_qty[]" class="form-control svc-qty" inputmode="decimal" min="0.01" step="0.01" value="1"></td>'+
        '<td><input type="text" class="form-control svc-unit-price" readonly value="0.00"></td>'+
        '<td><input type="text" class="form-control svc-line-total" readonly value="0.00"></td>'+
        '<td><button type="button" class="btn btn-xs btn-danger btnRemoveSvc"><i class="fa fa-times"></i></button></td>'+
    '</tr>');

    row.find('.btnRemoveSvc').on('click', function(){
        $(this).closest('tr').remove();
        recalcSvcTotals();
    });
    row.find('.svc-qty').on('input', function(){
        // Allow only numeric input with decimal point
        var val = $(this).val();
        var cleanVal = val.replace(/[^0-9.]/g, '');
        // Ensure only one decimal point
        var parts = cleanVal.split('.');
        if (parts.length > 2) {
            cleanVal = parts[0] + '.' + parts.slice(1).join('');
        }
        if (val !== cleanVal) {
            $(this).val(cleanVal);
        }
        recalcSvcTotals();
    });
    row.find('.svc-item-search').autocomplete({
        source: function(request, response) {
            var st = $('#service_type_hidden').val();
            $.getJSON(svcSearchUrl(st), { q: request.term, pm: $('#payment_method').val() }, function(data){
                response($.map(data, function(it){
                    return {
                        label: it.label + ' | GHS ' + formatMoney(it.unit_price),
                        value: it.value,
                        item_id: it.item_id,
                        unit_price: it.unit_price
                    };
                }));
            });
        },
        minLength: 2,
        select: function(event, ui) {
            row.find('.svc-item-id').val(ui.item.item_id);
            row.find('.svc-unit-price').val(formatMoney(ui.item.unit_price));
            recalcSvcTotals();
        }
    });

    if (prefill.label) {
        row.find('.svc-item-search').val(prefill.label);
    }
    if (prefill.item_id) {
        row.find('.svc-item-id').val(prefill.item_id);
    }
    if (prefill.qty) {
        row.find('.svc-qty').val(prefill.qty);
    }
    if (prefill.unit_price) {
        row.find('.svc-unit-price').val(formatMoney(prefill.unit_price));
    }

    $('#svcItemsBody').append(row);
    recalcSvcTotals();
}

function recalcPharmTotals() {
    var total = 0;
    $('#pharmItemsBody tr').each(function(){
        var qty = parseFloat($(this).find('.pharm-qty').val() || 0);
        var price = parseFloat($(this).find('.pharm-unit-price').val() || 0);
        var line = qty * price;
        if (!isNaN(line)) {
            total += line;
        }
        $(this).find('.pharm-line-total').val(formatMoney(line));
    });
    $('#pharmTotal').text(formatMoney(total));
    $('#amount').val(formatMoney(total));
}

function addPharmRow(prefill) {
    prefill = prefill || {};
    var row = $('<tr>'+
        '<td>'+
            '<input type="text" class="form-control pharm-drug-search" placeholder="Search drug..." autocomplete="off">' +
            '<input type="hidden" name="pharmacy_drug_id[]" class="pharm-drug-id" value="">' +
        '</td>'+
        '<td><input type="text" name="pharmacy_qty[]" class="form-control pharm-qty" inputmode="decimal" min="0.01" step="0.01" value="1"></td>'+
        '<td><input type="text" name="pharmacy_unit_price[]" class="form-control pharm-unit-price" readonly value="0.00"></td>'+
        '<td><input type="text" class="form-control pharm-line-total" readonly value="0.00"></td>'+
        '<td><button type="button" class="btn btn-xs btn-danger btnRemovePharm"><i class="fa fa-times"></i></button></td>'+
    '</tr>');

    row.find('.btnRemovePharm').on('click', function(){
        $(this).closest('tr').remove();
        recalcPharmTotals();
    });

    row.find('.pharm-qty').on('input', function(){
        // Allow only numeric input with decimal point
        var val = $(this).val();
        var cleanVal = val.replace(/[^0-9.]/g, '');
        // Ensure only one decimal point
        var parts = cleanVal.split('.');
        if (parts.length > 2) {
            cleanVal = parts[0] + '.' + parts.slice(1).join('');
        }
        if (val !== cleanVal) {
            $(this).val(cleanVal);
        }
        recalcPharmTotals();
    });

    row.find('.pharm-drug-search').autocomplete({
        source: function(request, response) {
            $.getJSON(WALKIN_BASE_URL + 'app/walkin/search_drugs_json', { q: request.term }, function(data){
                response($.map(data, function(it){
                    return {
                        label: it.label + ' | Stock: ' + it.nStock + ' | GHS ' + it.nPrice,
                        value: it.value,
                        drug_id: it.drug_id,
                        nStock: it.nStock,
                        nPrice: it.nPrice
                    };
                }));
            });
        },
        minLength: 2,
        select: function(event, ui) {
            row.find('.pharm-drug-id').val(ui.item.drug_id);
            if (parseFloat(row.find('.pharm-unit-price').val() || 0) <= 0) {
                row.find('.pharm-unit-price').val(formatMoney(ui.item.nPrice));
            }
            recalcPharmTotals();
        }
    });

    if (prefill.label) {
        row.find('.pharm-drug-search').val(prefill.label);
    }
    if (prefill.drug_id) {
        row.find('.pharm-drug-id').val(prefill.drug_id);
    }
    if (prefill.qty) {
        row.find('.pharm-qty').val(prefill.qty);
    }
    if (prefill.unit_price) {
        row.find('.pharm-unit-price').val(formatMoney(prefill.unit_price));
    }

    $('#pharmItemsBody').append(row);
    recalcPharmTotals();
}

function selectService(el, val) {
    walkinSaveCurrentCart(walkinCurrentServiceType);
    document.querySelectorAll('.service-btn').forEach(function(b){ b.classList.remove('selected'); });
    el.classList.add('selected');
    document.getElementById('service_type_hidden').value = val;
    walkinCurrentServiceType = val;
    // Set description placeholder based on service
    var placeholders = {
        'Laboratory': 'e.g. Full Blood Count, Malaria RDT, Urinalysis...',
        'Sonography': 'e.g. Abdominal Scan, Obstetric Scan...',
        'Radiology': 'e.g. X-Ray Chest PA, CT Scan Head, MRI Brain...',
        'Pharmacy': 'e.g. Amoxicillin 500mg x 21 tabs, Paracetamol...',
        'Procedure': 'e.g. Wound dressing, IV line insertion, Suturing...',
        'Consultation': 'e.g. General consultation, Follow-up visit...',
        'Other': 'Describe the service provided...'
    };
    document.getElementById('description').placeholder = placeholders[val] || 'Describe the service...';

    if (val === 'Pharmacy') {
        $('#pharmBox').show();
        $('#svcBox').hide();
        // For pharmacy: remove manual required fields; amount is computed
        $('#description').prop('required', false).val('');
        $('#amount').prop('required', false).prop('readonly', true);

        // Exclude NHIS for pharmacy
        $('#optNhis').prop('disabled', true).hide();
        if ($('#payment_method').val() === 'NHIS') {
            $('#payment_method').val('Cash');
        }

        $('#pharmItemsBody').empty();
        var stored = walkinCartStore[val] ? walkinCartStore[val].pharm : [];
        if (stored && stored.length) {
            $.each(stored, function(_, r){ addPharmRow(r); });
        }
        if ($('#pharmItemsBody tr').length === 0) {
            addPharmRow();
        }
        recalcPharmTotals();
    } else {
        $('#pharmBox').hide();
        $('#svcBox').show();
        $('#description').prop('required', false);
        $('#amount').prop('required', false).prop('readonly', true);
        $('#optNhis').prop('disabled', false).show();

        $('#svcItemsBody').empty();
        var storedSvc = walkinCartStore[val] ? walkinCartStore[val].svc : [];
        if (storedSvc && storedSvc.length) {
            $.each(storedSvc, function(_, r){ addSvcRow(r); });
        }
        if ($('#svcItemsBody tr').length === 0) {
            addSvcRow();
        }
        recalcSvcTotals();
    }
}
// Default select Laboratory
document.addEventListener('DOMContentLoaded', function(){
    var defaultBtn = document.querySelector('[data-service="Laboratory"]');
    if (defaultBtn) selectService(defaultBtn, 'Laboratory');
});
$('#frmTransaction').on('submit', function(){
    walkinSaveCurrentCart(walkinCurrentServiceType);
    if (!$('#service_type_hidden').val()) {
        alert('Please select a service type.');
        return false;
    }
    if ($('#service_type_hidden').val() === 'Pharmacy') {
        // Validate item rows have drug_id and qty
        var ok = true;
        if ($('#pharmItemsBody tr').length === 0) {
            alert('Please add at least one pharmacy item.');
            return false;
        }
        $('#pharmItemsBody tr').each(function(){
            var did = $(this).find('.pharm-drug-id').val();
            var qty = parseFloat($(this).find('.pharm-qty').val() || 0);
            if (!did || parseInt(did, 10) <= 0 || qty <= 0) {
                ok = false;
            }
        });
        if (!ok) {
            alert('Please ensure all pharmacy items have a selected drug and quantity.');
            return false;
        }
        recalcPharmTotals();
    } else {
        // Validate service items
        var okSvc = true;
        if ($('#svcItemsBody tr').length === 0) {
            alert('Please add at least one service item.');
            return false;
        }
        $('#svcItemsBody tr').each(function(){
            var iid = $(this).find('.svc-item-id').val();
            var qty = parseFloat($(this).find('.svc-qty').val() || 0);
            if (!iid || parseInt(iid, 10) <= 0 || qty <= 0) {
                okSvc = false;
            }
        });
        if (!okSvc) {
            alert('Please ensure all service items have a selected item and quantity.');
            return false;
        }
        recalcSvcTotals();
    }
    $('#btnPay').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Processing...');
});

$('#btnAddPharmItem').on('click', function(){
    addPharmRow();
});

$('#btnAddSvcItem').on('click', function(){
    addSvcRow();
});
</script>
</body>
</html>
