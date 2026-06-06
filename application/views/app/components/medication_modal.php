<?php
/**
 * Unified Medication Modal — Phase 3
 * GHS / NHIS Standard Prescription Interface
 *
 * INCLUDE REQUIREMENTS (must be set before including this file):
 *   $mm_opd_no      — URL-safe visit ID (used in save route)
 *   $mm_patient_no  — patient number
 *   $mm_save_url    — full base URL to save endpoint (e.g. base_url().'app/opd/save_medication_batch')
 *   $mm_module      — 'opd' | 'ipd' | 'nurse'   (used for modal ID namespacing)
 *   $mm_trigger_id  — HTML id of the element that opens this modal (for JS wiring)
 *
 * OPTIONAL:
 *   $mm_drug_categories — array of objects with drug_cat_id / drug_category
 *   $mm_is_nhis         — bool, true if patient is NHIS member
 *   $mm_diagnosis_code  — pre-filled diagnosis code from visit
 *   $mm_diagnosis_text  — pre-filled diagnosis description
 *
 * OUTPUT:
 *   Renders #unifiedMedModal-{$mm_module}
 *   Includes all JS wiring for this modal instance.
 *
 * CONTROLLERS NOT MODIFIED — submits to existing save_medication_batch endpoint.
 */

/* Safe defaults */
$mm_module       = isset($mm_module)       ? $mm_module       : 'opd';
$mm_opd_no       = isset($mm_opd_no)       ? $mm_opd_no       : '';
$mm_patient_no   = isset($mm_patient_no)   ? $mm_patient_no   : '';
$mm_save_url     = isset($mm_save_url)     ? $mm_save_url     : '';
$mm_is_nhis      = isset($mm_is_nhis)      ? (bool)$mm_is_nhis : false;
$mm_diagnosis_code = isset($mm_diagnosis_code) ? $mm_diagnosis_code : '';
$mm_diagnosis_text = isset($mm_diagnosis_text) ? $mm_diagnosis_text : '';
$mm_modal_id     = 'unifiedMedModal-' . $mm_module;
$mm_ns           = 'mm_' . $mm_module;   /* JS namespace prefix */
$mm_payer_type   = $mm_is_nhis ? 'NHIS' : 'CASH';
?>

<!-- ============================================================
     UNIFIED MEDICATION MODAL — <?php echo strtoupper($mm_module); ?>
     Phase 3 · GHS/NHIS Standard
     ============================================================ -->
<div class="modal fade" id="<?php echo $mm_modal_id; ?>" tabindex="-1" role="dialog"
     aria-labelledby="<?php echo $mm_modal_id; ?>Label" aria-hidden="true"
     data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog" style="width:96%;max-width:1080px;" role="document">
        <div class="modal-content" style="border:none;border-radius:8px;overflow:hidden;">

            <!-- ---- Header ---- -->
            <div class="mm-modal-header">
                <button type="button" class="close" data-dismiss="modal"
                        style="color:#fff;opacity:.8;margin-top:-2px;">&times;</button>
                <h4>
                    <i class="fa fa-medkit"></i>
                    &nbsp;Prescription Entry
                    <?php if ($mm_is_nhis): ?>
                        <span class="mm-nhis-badge"><i class="fa fa-shield"></i> NHIS</span>
                    <?php endif; ?>
                    <small style="font-size:12px;font-weight:400;opacity:.85;margin-left:8px;">
                        GHS Standard &bull; <?php echo strtoupper($mm_module); ?> Visit
                    </small>
                </h4>
            </div>

            <!-- ---- Body ---- -->
            <div class="modal-body" style="background:#f4f6f9;padding:16px;max-height:78vh;overflow-y:auto;">

                <?php if (isset($this->security)): ?>
                    <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>"
                           value="<?php echo $this->security->get_csrf_hash(); ?>">
                <?php endif; ?>

                <!-- Summary bar -->
                <div class="mm-summary-bar">
                    <div>
                        <span class="count" id="<?php echo $mm_ns; ?>-count">0</span>
                        <span class="label-text">&nbsp;medication(s) to prescribe</span>
                    </div>
                    <button type="button" class="mm-btn-add" id="<?php echo $mm_ns; ?>-btn-add">
                        <i class="fa fa-plus"></i>&nbsp; Add Medication
                    </button>
                </div>

                <!-- Entry container -->
                <div id="<?php echo $mm_ns; ?>-container">
                    <div class="empty-state" style="text-align:center;padding:36px 20px;color:#aaa;">
                        <i class="fa fa-pills" style="font-size:42px;margin-bottom:12px;opacity:.4;"></i>
                        <p style="font-size:13px;">Click <strong>Add Medication</strong> to start prescribing</p>
                    </div>
                </div>

            </div><!-- /.modal-body -->

            <!-- ---- Footer ---- -->
            <div class="modal-footer" style="background:#f4f6f9;border-top:1px solid #e0e0e0;">
                <span id="<?php echo $mm_ns; ?>-save-status" style="display:none;margin-right:12px;color:#888;">
                    <i class="fa fa-spinner fa-spin"></i> Saving…
                </span>
                <button type="button" class="btn btn-default" data-dismiss="modal">
                    <i class="fa fa-times"></i> Cancel
                </button>
                <button type="button" class="mm-btn-save btn" id="<?php echo $mm_ns; ?>-btn-save" disabled>
                    <i class="fa fa-check-circle"></i>&nbsp; Save All Prescriptions
                </button>
            </div>

        </div><!-- /.modal-content -->
    </div><!-- /.modal-dialog -->
</div><!-- /#<?php echo $mm_modal_id; ?> -->


<!-- ============================================================
     UNIFIED MEDICATION MODAL JS — <?php echo strtoupper($mm_module); ?>
     ============================================================ -->
<script>
(function () {
    'use strict';

    /* ---- module config (PHP → JS) ---- */
    var NS        = <?php echo json_encode($mm_ns); ?>;
    var MODAL_ID  = <?php echo json_encode('#' . $mm_modal_id); ?>;
    var MODULE    = <?php echo json_encode($mm_module); ?>;
    var SAVE_URL  = <?php echo json_encode($mm_save_url); ?>;
    var OPD_NO    = <?php echo json_encode($mm_opd_no); ?>;
    var PAT_NO    = <?php echo json_encode($mm_patient_no); ?>;
    var IS_NHIS   = <?php echo json_encode($mm_is_nhis); ?>;
    var DIAG_CODE = <?php echo json_encode($mm_diagnosis_code); ?>;

    var $modal     = $(MODAL_ID);
    var $container = $('#' + NS + '-container');
    var $addBtn    = $('#' + NS + '-btn-add');
    var $saveBtn   = $('#' + NS + '-btn-save');
    var $countEl   = $('#' + NS + '-count');
    var $status    = $('#' + NS + '-save-status');
    var rowIdx     = 0;

    /* ---- ensure MedicationModal lib is ready ---- */
    if (typeof MedicationModal === 'undefined') {
        console.error('[UnifiedMedModal] MedicationModal JS not loaded. Include /public/js/medication-modal.js before this component.');
        return;
    }
    var STRUCTURED_STRENGTH_ENABLED = <?php echo json_encode((bool)$this->config->item('ENABLE_STRUCTURED_STRENGTH_CALCULATION')); ?>;
    MedicationModal.init(<?php echo json_encode(base_url()); ?>, {
        structured_strength_enabled: STRUCTURED_STRENGTH_ENABLED
    });

    /* ================================================================
     * ROW TEMPLATE
     * ============================================================= */
    function buildRowHtml(idx) {
        return [
            '<div class="mm-row" data-idx="' + idx + '">',
            '  <span class="row-number">' + idx + '</span>',
            '  <button type="button" class="btn btn-danger btn-xs btn-remove-row" style="position:absolute;right:8px;top:8px;" title="Remove"><i class="fa fa-times"></i></button>',

            /* == SECTION 1: Medication selection == */
            '  <div class="mm-section-title"><i class="fa fa-search"></i> 1. Medication</div>',
            '  <div class="row">',
            '    <div class="col-md-7">',
            '      <div class="form-group" style="margin-bottom:6px;">',
            '        <label style="font-size:11px;color:#555;">Drug Name <span class="text-danger">*</span></label>',
            '        <input type="text" class="form-control input-sm drug-search" placeholder="Search by brand name, generic name, code…" autocomplete="off" style="border-radius:4px;">',
            '        <input type="hidden" class="drug-id" name="drug_name[]" value="">',
            '        <input type="hidden" class="drug-text" name="medicine_text[]" value="">',
            '        <div class="drug-stock-info" style="font-size:11px;margin-top:3px;color:#888;"></div>',
            '      </div>',
            '    </div>',
            '    <div class="col-md-5">',
            '      <div class="form-group" style="margin-bottom:6px;">',
            '        <label style="font-size:11px;color:#555;">Drug Form</label>',
            '        <select class="form-control input-sm med-form" name="drug_form[]">',
            '          <option value="">-- Form --</option>',
            '        </select>',
            '      </div>',
            '    </div>',
            '  </div>',

            /* == SECTION 2: Dosage == */
            '  <div class="mm-section-title"><i class="fa fa-flask"></i> 2. Dosage</div>',
            '  <div class="row">',
            '    <div class="col-md-3">',
            '      <div class="form-group" style="margin-bottom:6px;">',
            '        <label style="font-size:11px;color:#555;">Dose <span class="text-danger">*</span></label>',
            '        <div class="mm-dose-group">',
            '          <input type="number" class="form-control input-sm med-dose mm-dose-input" name="dosage[]" placeholder="e.g. 500" min="0" step="0.1">',
            '          <select class="form-control input-sm med-unit mm-unit-sel" name="unit[]">',
            '            <option value="">Unit</option>',
            '          </select>',
            '        </div>',
            '      </div>',
            '      <div class="form-group" style="margin-bottom:6px;">',
            '        <label style="font-size:11px;color:#555;">Strength per unit</label>',
            '        <div class="mm-dose-group">',
            '          <input type="number" class="form-control input-sm med-strength-value" name="strength_per_unit_value[]" placeholder="e.g. 500" min="0" step="0.1">',
            '          <select class="form-control input-sm med-strength-unit" name="strength_per_unit_unit[]">',
            '            <option value="">Unit</option>',
            '          </select>',
            '        </div>',
            '      </div>',
            '    </div>',
            '    <div class="col-md-3">',
            '      <div class="form-group" style="margin-bottom:6px;">',
            '        <label style="font-size:11px;color:#555;">Frequency <span class="text-danger">*</span></label>',
            '        <select class="form-control input-sm med-freq" name="freq_code[]">',
            '          <option value="">-- Frequency --</option>',
            '        </select>',
            '        <input type="hidden" name="frequency[]" value="">',
            '      </div>',
            '    </div>',
            '    <div class="col-md-3">',
            '      <div class="form-group" style="margin-bottom:6px;">',
            '        <label style="font-size:11px;color:#555;">Route</label>',
            '        <select class="form-control input-sm med-route" name="route[]">',
            '          <option value="">-- Route --</option>',
            '        </select>',
            '      </div>',
            '    </div>',
            '    <div class="col-md-3">',
            '      <div class="form-group" style="margin-bottom:6px;">',
            '        <label style="font-size:11px;color:#555;">Duration (Days) <span class="text-danger">*</span></label>',
            '        <input type="number" class="form-control input-sm med-days" name="days[]" value="1" min="1" max="365" placeholder="Days">',
            '      </div>',
            '    </div>',
            '  </div>',

            /* == SECTION 3: Quantity calculation == */
            '  <div class="mm-section-title"><i class="fa fa-calculator"></i> 3. Quantity</div>',
            '  <div class="row" style="align-items:center;">',
            '    <div class="col-md-3">',
            '      <div class="form-group" style="margin-bottom:6px;">',
            '        <label style="font-size:11px;color:#555;">Required Units</label>',
            '        <div class="mm-qty-row">',
            '          <input type="number" class="form-control input-sm med-qty" name="total_qty[]" value="" min="0" step="0.01" placeholder="Auto" readonly style="border-radius:4px;">',
            '        </div>',
            '        <input type="hidden" class="mm-required-units" name="required_units[]" value="">',
            '        <input type="hidden" class="mm-total-active-mass-value" name="total_active_mass_value[]" value="">',
            '        <input type="hidden" class="mm-total-active-mass-unit" name="total_active_mass_unit[]" value="">',
            '        <div class="mm-qty-auto"></div>',
            '        <div class="mm-active-mass" style="font-size:11px;color:#3c8dbc;font-weight:600;"></div>',
            '        <label class="mm-qty-override-label" style="margin-top:4px;">',
            '          <input type="checkbox" class="med-qty-override"> Override quantity',
            '        </label>',
            '      </div>',
            '    </div>',
            '    <div class="col-md-9">',
            '      <div class="mm-high-dose-warn"></div>',
            '    </div>',
            '  </div>',

            /* == SECTION 4: Instructions == */
            '  <div class="mm-section-title"><i class="fa fa-comment"></i> 4. Instructions</div>',
            '  <div class="row">',
            '    <div class="col-md-8">',
            '      <div class="form-group" style="margin-bottom:6px;">',
            '        <textarea class="form-control input-sm med-instruction" name="instruction[]" rows="2" placeholder="Instructions to patient (e.g. Take after meals, Complete full course, Avoid alcohol)"></textarea>',
            '      </div>',
            '    </div>',
            '    <div class="col-md-4">',
            '      <div class="form-group" style="margin-bottom:6px;">',
            '        <label style="font-size:11px;color:#555;">Additional Advice</label>',
            '        <input type="text" class="form-control input-sm" name="advice[]" placeholder="e.g. Avoid driving">',
            '      </div>',
            '    </div>',
            '  </div>',

            /* == SECTION 5: Additional Fields == */
            '  <div class="mm-section-title"><i class="fa fa-list-ul"></i> 5. Additional</div>',
            '  <div class="row">',
            '    <div class="col-md-6">',
            '      <div class="form-group" style="margin-bottom:4px;">',
            '        <label style="font-size:11px;color:#555;">Diagnosis (auto from visit)</label>',
            '        <input type="text" class="form-control input-sm" name="diagnosis_search_row[]" placeholder="Search ICD-10…" autocomplete="off" value="' + escHtml(DIAG_CODE) + '">',
            '        <input type="hidden" name="diagnosis_code[]" class="row-diag-code" value="' + escHtml(DIAG_CODE) + '">',
            '      </div>',
            '    </div>',
            '    <div class="col-md-6">',
            '      <div style="margin-top:20px;">',
            '        <label class="mm-qty-override-label" style="margin-right:14px;">',
            IS_NHIS ?
            '          <input type="checkbox" name="is_nhis_covered[]" value="1" checked> NHIS Covered' :
            '          <input type="checkbox" name="is_nhis_covered[]" value="1"> NHIS Covered',
            '        </label>',
            '        <label class="mm-qty-override-label" style="margin-right:14px;">',
            '          <input type="checkbox" name="is_prn[]" value="1"> PRN (As needed)',
            '        </label>',
            '        <label class="mm-qty-override-label">',
            '          <input type="checkbox" name="is_urgent[]" value="1"> Urgent',
            '        </label>',
            '      </div>',
            '    </div>',
            '  </div>',

            /* == SECTION 6: Live preview == */
            '  <div class="mm-preview-box">',
            '    <div class="mm-preview-label"><i class="fa fa-eye"></i> Prescription Preview</div>',
            '    <div class="med-preview-content"></div>',
            '  </div>',

            '</div><!-- /.mm-row -->'
        ].join('\n');
    }

    function escHtml(str) {
        if (!str) return '';
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    /* ================================================================
     * ADD ENTRY
     * ============================================================= */
    function addRow() {
        rowIdx++;
        $container.find('.empty-state').remove();
        $container.append(buildRowHtml(rowIdx));

        var $row = $container.find('.mm-row:last');
        _bindRow($row);
        _updateCount();
        $saveBtn.prop('disabled', false);

        /* Scroll into view */
        var $body = $modal.find('.modal-body');
        $body.animate({ scrollTop: $body[0].scrollHeight }, 200);
    }

    /* ================================================================
     * BIND A ROW
     * ============================================================= */
    function _bindRow($row) {
        /* Remove button */
        $row.find('.btn-remove-row').on('click', function () {
            $row.fadeOut(180, function () {
                $(this).remove();
                _renumber();
                _updateCount();
                if ($container.find('.mm-row').length === 0) {
                    $saveBtn.prop('disabled', true);
                    $container.html('<div class="empty-state" style="text-align:center;padding:36px 20px;color:#aaa;"><i class="fa fa-pills" style="font-size:42px;margin-bottom:12px;opacity:.4;"></i><p style="font-size:13px;">Click <strong>Add Medication</strong> to start prescribing</p></div>');
                }
            });
        });

        /* Drug autocomplete */
        var $search  = $row.find('.drug-search');
        var $drugId  = $row.find('.drug-id');
        var $drugTxt = $row.find('.drug-text');
        var $stockInfo = $row.find('.drug-stock-info');

        var MM_PAYER_TYPE = <?php echo json_encode($mm_payer_type); ?>;

        if ($search.data('ui-autocomplete') || $search.data('autocomplete')) {
            try { $search.autocomplete('destroy'); } catch (e) {}
        }

        $search.autocomplete({
            appendTo: $modal,
            source: function (request, response) {
                $.ajax({
                    url: <?php echo json_encode(base_url() . 'app/pharmacy/drug_search_json'); ?>,
                    type: 'GET',
                    data: { term: request.term },
                    dataType: 'json',
                    success: function (data) {
                        var results = [];
                        if (data && data.length) {
                            data.forEach(function (item) {
                                // API contract (app/pharmacy/drug_search_json):
                                //   { id, label, drug_name, generic_name, category, stock, price, nhis_covered, nhis_price, cash_price, in_stock }
                                var stock = (typeof item.stock !== 'undefined') ? item.stock : (item.nStock || 0);
                                var cashPrice = (typeof item.cash_price !== 'undefined') ? item.cash_price : 0;
                                var nhisPrice = (typeof item.nhis_price !== 'undefined') ? item.nhis_price : 0;
                                var basePrice = (typeof item.price !== 'undefined') ? item.price : (item.nPrice || 0);
                                var isNhisCovered = (parseInt(item.nhis_covered || item.is_nhis_covered || 0, 10) === 1);
                                var displayPrice = basePrice;
                                if (MM_PAYER_TYPE === 'NHIS') {
                                    // Prefer NHIS tariff if covered and available; otherwise fall back to cash/base price.
                                    if (isNhisCovered && parseFloat(nhisPrice || 0) > 0) displayPrice = nhisPrice;
                                    else if (parseFloat(cashPrice || 0) > 0) displayPrice = cashPrice;
                                } else {
                                    // CASH/PRIVATE: prefer cash_price if set; else basePrice.
                                    if (parseFloat(cashPrice || 0) > 0) displayPrice = cashPrice;
                                }

                                results.push({
                                    label: (item.label ? item.label : (item.drug_name || '')),
                                    value: (item.drug_name ? item.drug_name : (item.label || '')),
                                    id:    (item.id ? item.id : (item.drug_id || 0)),
                                    stock: stock || 0,
                                    price: displayPrice || 0,
                                    // Pass through canonical fields for auto-populate (if your backend includes them later)
                                    dosage_form: item.dosage_form || '',
                                    route:        item.route       || '',
                                    standard_dosage: item.standard_dosage || '',
                                    nhis_covered: isNhisCovered ? 1 : 0,
                                    nhis_price: nhisPrice || 0,
                                    cash_price: cashPrice || 0
                                });
                            });
                        }
                        response(results);
                    }
                });
            },
            minLength: 2,
            open: function () {
                $(this).autocomplete('widget').css('z-index', 1051);
            },
            select: function (event, ui) {
                $drugId.val(ui.item.id);
                $drugTxt.val(ui.item.value);
                // Because we return false (to keep full control), we must explicitly set the visible
                // search box value; otherwise it stays as whatever the user typed ("amo"), which
                // looks like the selection did not apply.
                $search.val(ui.item.value || ui.item.label || '');
                var stockCls = ui.item.stock > 0 ? 'text-success' : 'text-danger';
                $stockInfo.html(
                    '<i class="fa fa-cubes ' + stockCls + '"></i> Stock: ' + ui.item.stock +
                    ' &nbsp;|&nbsp; <i class="fa fa-money text-info"></i> GH\u20b5' + parseFloat(ui.item.price || 0).toFixed(2)
                );
                /* Trigger MedicationModal auto-populate */
                $row.trigger('drug-selected', [ui.item]);
                return false;
            }
        });
        var acInst = null;
        try {
            acInst = $search.autocomplete('instance');
        } catch (e) {
            acInst = null;
        }
        if (!acInst) {
            acInst = $search.data('ui-autocomplete') || $search.data('autocomplete') || null;
        }
        if (acInst) {
            acInst._renderItem = function (ul, item) {
                var html = '' +
                    '<strong>' + MedicationModal.escapeHtml(item.label) + '</strong>' +
                    (item.stock > 0
                        ? ' <span style="float:right;font-size:11px;color:#27ae60;"><i class="fa fa-check-circle"></i> ' + item.stock + ' in stock</span>'
                        : ' <span style="float:right;font-size:11px;color:#e74c3c;"><i class="fa fa-times-circle"></i> Out of stock</span>');
                return $('<li>').append(
                    $('<a></a>')
                        .attr('href', '#')
                        .css({ display: 'block', padding: '4px 8px', fontSize: '13px', cursor: 'pointer' })
                        .html(html)
                ).appendTo(ul);
            };
        }

        /* Bind Phase 3 calculation / preview / warnings */
        MedicationModal.bindRow($row, {
            onHighDose: function (warn) {
                /* already shown inline; could trigger extra alert here */
            }
        });

        /* Per-row diagnosis ICD search */
        var $diagSearch = $row.find('[name="diagnosis_search_row[]"]');
        var $diagCode   = $row.find('.row-diag-code');
        var diagTimer;
        $diagSearch.on('input', function () {
            clearTimeout(diagTimer);
            var term = $(this).val();
            if (term.length < 2) return;
            diagTimer = setTimeout(function () {
                $.getJSON(<?php echo json_encode(base_url() . 'app/opd/search_diagnosis_json'); ?>, { term: term }, function (data) {
                    if (!data || !data.length) return;
                    var list = $('<ul class="list-group" style="position:absolute;z-index:9999;width:90%;max-height:160px;overflow-y:auto;background:#fff;border:1px solid #ddd;border-radius:4px;"></ul>');
                    data.slice(0, 8).forEach(function (d) {
                        $('<li class="list-group-item" style="padding:5px 10px;cursor:pointer;font-size:12px;"><strong>' + MedicationModal.escapeHtml(d.code) + '</strong> ' + MedicationModal.escapeHtml(d.description) + '</li>')
                            .on('click', function () {
                                $diagSearch.val(d.code + ' \u2014 ' + d.description);
                                $diagCode.val(d.code);
                                list.remove();
                            })
                            .appendTo(list);
                    });
                    $diagSearch.after(list);
                    $(document).one('click', function () { list.remove(); });
                });
            }, 280);
        });
    }

    /* ================================================================
     * COLLECT ENTRIES (backward-compatible with save_medication_batch)
     * ============================================================= */
    function collectEntries() {
        var entries = [];
        $container.find('.mm-row').each(function () {
            var $r = $(this);
            var drugId   = $r.find('.drug-id').val();
            var drugTxt  = $r.find('.drug-text').val() || $r.find('.drug-search').val() || '';
            if (!drugId && !drugTxt) return;   /* skip blank rows */

            entries.push({
                drug_name:    drugId,
                medicine_text: drugTxt,
                dosage:       $r.find('[name="dosage[]"]').val()     || '',
                unit:         $r.find('[name="unit[]"]').val()        || '',
                strength_per_unit_value: parseFloat($r.find('[name="strength_per_unit_value[]"]').val()) || 0,
                strength_per_unit_unit: $r.find('[name="strength_per_unit_unit[]"]').val() || '',
                frequency:    $r.find('[name="frequency[]"]').val()   || $r.find('.med-freq').val() || '',
                freq_code:    $r.find('.med-freq').val()              || '',
                route:        $r.find('[name="route[]"]').val()       || '',
                drug_form:    $r.find('[name="drug_form[]"]').val()   || '',
                days:         parseInt($r.find('[name="days[]"]').val()) || 1,
                total_qty:    parseFloat($r.find('[name="total_qty[]"]').val()) || 0,
                required_units: parseFloat($r.find('[name="required_units[]"]').val()) || 0,
                total_active_mass_value: parseFloat($r.find('[name="total_active_mass_value[]"]').val()) || 0,
                total_active_mass_unit: $r.find('[name="total_active_mass_unit[]"]').val() || '',
                instruction:  $r.find('[name="instruction[]"]').val() || '',
                advice:       $r.find('[name="advice[]"]').val()      || '',
                diagnosis_code: $r.find('.row-diag-code').val()      || '',
                is_nhis_covered: $r.find('[name="is_nhis_covered[]"]').is(':checked') ? 1 : 0,
                is_prn:       $r.find('[name="is_prn[]"]').is(':checked') ? 1 : 0,
                is_urgent:    $r.find('[name="is_urgent[]"]').is(':checked') ? 1 : 0
            });
        });
        return entries;
    }

    /* ================================================================
     * SAVE ALL
     * ============================================================= */
    $saveBtn.on('click', function () {
        var entries = collectEntries();
        if (!entries.length) {
            alert('Please add at least one medication before saving.');
            return;
        }

        $saveBtn.prop('disabled', true);
        $status.show();

        /* CSRF */
        var csrfName  = '';
        var csrfToken = '';
        var $csrfIn   = $modal.find('input[name$="_token"]');
        if ($csrfIn.length) {
            csrfName  = $csrfIn.attr('name');
            csrfToken = $csrfIn.val();
        }

        var postData = {
            opd_no:    OPD_NO,
            patient_no: PAT_NO,
            entries:   JSON.stringify(entries)
        };
        if (csrfName) postData[csrfName] = csrfToken;

        $.ajax({
            url:      SAVE_URL,
            type:     'POST',
            data:     postData,
            dataType: 'json',
            success: function (response) {
                $status.hide();
                $saveBtn.prop('disabled', false);

                if (response.blocked) {
                    var msg = '';
                    if (response.details && response.details.length) {
                        response.details.forEach(function (d) {
                            msg += '<li><strong>' + MedicationModal.escapeHtml(d.type) + ':</strong> ' + MedicationModal.escapeHtml(d.message) + '</li>';
                        });
                    }
                    $modal.find('.modal-body').prepend(
                        '<div class="alert alert-danger alert-dismissable"><button type="button" class="close" data-dismiss="alert">&times;</button>' +
                        '<i class="fa fa-ban"></i> <strong>Prescription Blocked:</strong><ul>' + msg + '</ul></div>'
                    );
                    return;
                }
                if (response.nhis_block) {
                    $modal.find('.modal-body').prepend(
                        '<div class="alert alert-danger alert-dismissable"><button type="button" class="close" data-dismiss="alert">&times;</button>' +
                        '<i class="fa fa-ban"></i> <strong>Payment Required:</strong> ' + MedicationModal.escapeHtml(response.message || '') + '</div>'
                    );
                    return;
                }
                if (response.success) {
                    $(MODAL_ID).modal('hide');
                    if (response.redirect) {
                        window.location.href = response.redirect;
                    } else {
                        window.location.reload();
                    }
                } else {
                    alert(response.message || 'Error saving medications. Please try again.');
                }
            },
            error: function (xhr) {
                $status.hide();
                $saveBtn.prop('disabled', false);
                var msg = 'Network error. Please try again.';
                try {
                    var r = JSON.parse(xhr.responseText);
                    if (r.message) msg = r.message;
                } catch (e) {}
                alert(msg);
            }
        });
    });

    /* ================================================================
     * HELPERS
     * ============================================================= */
    function _updateCount() {
        var n = $container.find('.mm-row').length;
        $countEl.text(n);
    }

    function _renumber() {
        $container.find('.mm-row').each(function (i) {
            $(this).find('.row-number').text(i + 1);
        });
    }

    /* ================================================================
     * MODAL LIFECYCLE
     * ============================================================= */
    $modal.on('shown.bs.modal', function () {
        /* Pre-warm master data on first open */
        MedicationModal.loadMasters(null);
        /* Auto-add first row */
        if ($container.find('.mm-row').length === 0) {
            addRow();
        }
    });

    $modal.on('hidden.bs.modal', function () {
        /* Reset on close */
        rowIdx = 0;
        $container.html('<div class="empty-state" style="text-align:center;padding:36px 20px;color:#aaa;"><i class="fa fa-pills" style="font-size:42px;margin-bottom:12px;opacity:.4;"></i><p style="font-size:13px;">Click <strong>Add Medication</strong> to start prescribing</p></div>');
        $countEl.text('0');
        $saveBtn.prop('disabled', true);
    });

    /* Add-row button */
    $addBtn.on('click', addRow);

    /* Expose to parent page if needed */
    window['UnifiedMedModal_' + MODULE] = {
        open:    function () { $(MODAL_ID).modal('show'); },
        addRow:  addRow,
        collect: collectEntries
    };

}());
</script>
