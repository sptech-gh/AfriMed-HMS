/**
 * MedicationModal — Phase 3
 * GHS/NHIS-aligned unified prescription modal
 *
 * Responsibilities:
 *  - Load route/form/unit masters from server (cached per page)
 *  - Auto-populate defaults when a drug is selected
 *  - Calculate quantity: dose_per_unit × frequency_per_day × days
 *  - Live prescription preview string
 *  - High-dose soft warning
 *  - Populate hidden fields required by existing save endpoints (backward-compatible)
 */
var MedicationModal = (function () {
    'use strict';

    /* -------------------------------------------------------------------------
     * GHS standard frequency master (code → label + doses/day)
     * Kept in JS as a fast local fallback; server master table takes precedence.
     * ----------------------------------------------------------------------- */
    var FREQ_MAP = {
        'OD':     { label: 'Once Daily',           dpd: 1  },
        'BD':     { label: 'Twice Daily',           dpd: 2  },
        'TDS':    { label: 'Three Times Daily',     dpd: 3  },
        'QID':    { label: 'Four Times Daily',      dpd: 4  },
        'Q4H':    { label: 'Every 4 Hours',         dpd: 6  },
        'Q6H':    { label: 'Every 6 Hours',         dpd: 4  },
        'Q8H':    { label: 'Every 8 Hours',         dpd: 3  },
        'Q12H':   { label: 'Every 12 Hours',        dpd: 2  },
        'STAT':   { label: 'Immediately (Once)',    dpd: 1  },
        'PRN':    { label: 'As Needed',             dpd: 0  },
        'ON':     { label: 'At Night (Nocte)',      dpd: 1  },
        'MN':     { label: 'Morning (Mane)',        dpd: 1  },
        'OW':     { label: 'Once Weekly',           dpd: 0.143 },
        'BIW':    { label: 'Twice Weekly',          dpd: 0.286 },
        'OM':     { label: 'Once Monthly',          dpd: 0  },
        'AC':     { label: 'Before Meals',          dpd: 3  },
        'PC':     { label: 'After Meals',           dpd: 3  }
    };

    /* High-dose thresholds (mg) — soft warning only, never blocks */
    var HIGH_DOSE_THRESHOLDS = {
        'paracetamol': 1000,
        'ibuprofen':   800,
        'aspirin':     1000,
        'amoxicillin': 1000,
        'metronidazole': 800,
        'default':     2000
    };

    /* Module state */
    var _baseUrl = '';
    var _routeMaster  = null;
    var _formMaster   = null;
    var _unitMaster   = null;
    var _freqMaster   = null;   /* from server (medication_frequency table) */
    var _mastersLoaded = false;
    var _loadingMasters = false;
    var _opts = {
        structured_strength_enabled: false
    };

    /* =========================================================================
     * PUBLIC API
     * ======================================================================= */

    /**
     * Initialise with base URL. Must be called once before anything else.
     */
    function init(baseUrl, opts) {
        _baseUrl = baseUrl || '';
        if (opts && typeof opts === 'object') {
            _opts.structured_strength_enabled = !!opts.structured_strength_enabled;
        }
        _injectStyles();
    }

    /**
     * Load all master data (route / form / unit / frequency) from server.
     * Caches in module-level vars. Calls cb(true) when done, cb(false) on error.
     */
    function loadMasters(cb) {
        if (_mastersLoaded) { if (cb) cb(true); return; }
        if (_loadingMasters) {
            /* Queue the callback to fire once loading finishes */
            setTimeout(function () { loadMasters(cb); }, 100);
            return;
        }
        _loadingMasters = true;

        $.ajax({
            url: _baseUrl + 'app/opd/get_medication_masters_json',
            type: 'GET',
            dataType: 'json',
            success: function (data) {
                _routeMaster  = (data && data.routes)     || [];
                _formMaster   = (data && data.forms)      || [];
                _unitMaster   = (data && data.units)      || [];
                _freqMaster   = (data && data.frequencies) || [];
                /* Merge server frequency data into FREQ_MAP */
                if (_freqMaster.length) {
                    _freqMaster.forEach(function (f) {
                        FREQ_MAP[f.code] = { label: f.label, dpd: parseFloat(f.doses_per_day) || 0 };
                    });
                }
                _mastersLoaded  = true;
                _loadingMasters = false;
                if (cb) cb(true);
            },
            error: function () {
                /* Graceful fallback: use built-in FREQ_MAP, empty route/form/unit */
                _routeMaster  = [];
                _formMaster   = [];
                _unitMaster   = [];
                _freqMaster   = [];
                _mastersLoaded  = true;
                _loadingMasters = false;
                if (cb) cb(false);
            }
        });
    }

    /**
     * Populate a <select> element with route options.
     * @param {jQuery} $sel
     * @param {string} [selected]
     */
    function populateRouteSelect($sel, selected) {
        _buildSelect($sel, _routeMaster || [], 'route', selected,
            ['Oral', 'IV', 'IM', 'Topical', 'Subcutaneous',
             'Inhalation', 'Sublingual', 'Rectal', 'Intranasal']);
    }

    /**
     * Populate a <select> element with drug-form options.
     */
    function populateFormSelect($sel, selected) {
        _buildSelect($sel, _formMaster || [], 'form', selected,
            ['Tablet', 'Capsule', 'Syrup', 'Injection', 'Suspension',
             'Cream', 'Ointment', 'Drops', 'Inhaler', 'Patch']);
    }

    /**
     * Populate a <select> element with unit options.
     */
    function populateUnitSelect($sel, selected) {
        _buildSelect($sel, _unitMaster || [], 'unit', selected,
            ['mg', 'g', 'mcg', 'ml', 'tablet', 'capsule', 'drop', 'puff', 'IU']);
    }

    /**
     * Populate a <select> element with GHS-standard frequency options.
     */
    function populateFreqSelect($sel, selected) {
        $sel.empty().append('<option value="">-- Frequency --</option>');
        Object.keys(FREQ_MAP).forEach(function (code) {
            var f = FREQ_MAP[code];
            var opt = $('<option>').val(code).text(code + ' \u2014 ' + f.label);
            if (selected && selected === code) opt.prop('selected', true);
            $sel.append(opt);
        });
    }

    /**
     * Calculate total quantity.
     * @param  {number} dosePerUnit   - amount per single dose (unit-less)
     * @param  {string} freqCode      - e.g. 'TDS'
     * @param  {number} days
     * @return {number|null}
     */
    function calculateQty(dosePerUnit, freqCode, days) {
        var f = FREQ_MAP[freqCode];
        if (!f || !f.dpd || !dosePerUnit || !days) return null;
        return Math.ceil(dosePerUnit * f.dpd * days);
    }

    function _parseStrengthSimple(strengthText) {
        var s = (strengthText || '').toString().trim();
        if (!s) return null;
        var m = s.match(/^\s*(\d+(?:\.\d+)?)\s*(mg|g|mcg|iu)\s*$/i);
        if (!m) return null;
        return { value: parseFloat(m[1]), unit: (m[2] || '').toLowerCase() };
    }

    function calculateTotalActiveMass(doseValue, freqCode, days) {
        var f = FREQ_MAP[freqCode];
        if (!f || !f.dpd) return null;
        var doseNum = parseFloat(doseValue);
        var d = parseFloat(days);
        if (isNaN(doseNum) || doseNum <= 0 || isNaN(d) || d <= 0) return null;
        return doseNum * f.dpd * d;
    }

    function calculateRequiredUnits(doseValue, strengthPerUnitValue, doseUnit, strengthUnit, freqCode, days) {
        var f = FREQ_MAP[freqCode];
        if (!f || !f.dpd) return null;
        var doseNum = parseFloat(doseValue);
        var strNum = parseFloat(strengthPerUnitValue);
        var d = parseFloat(days);
        var du = (doseUnit || '').toString().trim().toLowerCase();
        var su = (strengthUnit || '').toString().trim().toLowerCase();
        if (isNaN(doseNum) || doseNum <= 0 || isNaN(strNum) || strNum <= 0 || isNaN(d) || d <= 0) return null;
        if (!du || !su || du !== su) return null;
        return (doseNum / strNum) * f.dpd * d;
    }

    /**
     * Generate a human-readable prescription preview string.
     * @param  {object} rx - {drugName, dose, unit, freqCode, form, days, qty}
     * @return {string}
     */
    function buildPreview(rx) {
        if (!rx.drugName) return '';
        var parts = [];

        var namePart = rx.drugName;
        if (rx.dose)  namePart += ' ' + rx.dose;
        if (rx.unit)  namePart += rx.unit;
        if (rx.form)  namePart += ' ' + rx.form;
        parts.push(namePart);

        var dosePart = 'Take';
        if (rx.dose)     dosePart += ' ' + rx.dose + (rx.unit ? rx.unit : '');
        if (rx.freqCode) {
            var fEntry = FREQ_MAP[rx.freqCode];
            dosePart += ' ' + (fEntry ? fEntry.label.toLowerCase() : rx.freqCode);
        }
        if (rx.days && parseInt(rx.days) > 0) {
            dosePart += ' for ' + rx.days + ' day' + (parseInt(rx.days) !== 1 ? 's' : '');
        }
        parts.push(dosePart);

        if (rx.qty) {
            var unitLabel = rx.form || (rx.unit ? rx.unit : 'unit');
            parts.push('Qty: ' + rx.qty + ' ' + unitLabel + (rx.qty > 1 && !rx.form ? 's' : ''));
        }
        if (rx.instruction) parts.push(rx.instruction);

        return parts.join('\n');
    }

    /**
     * Check if a dose is abnormally high for a given drug name.
     * Soft-warning only — returns {high: bool, threshold: number, message: string}
     */
    function checkHighDose(drugName, doseValue, unit) {
        if (!drugName || !doseValue) return { high: false };
        if (unit && unit.toLowerCase() !== 'mg') return { high: false };

        var name = drugName.toLowerCase();
        var threshold = HIGH_DOSE_THRESHOLDS['default'];
        Object.keys(HIGH_DOSE_THRESHOLDS).forEach(function (key) {
            if (name.indexOf(key) !== -1) threshold = HIGH_DOSE_THRESHOLDS[key];
        });

        var dose = parseFloat(doseValue);
        if (isNaN(dose) || dose <= threshold) return { high: false };

        return {
            high: true,
            threshold: threshold,
            message: 'High dose detected: ' + dose + 'mg exceeds typical maximum of ' + threshold + 'mg. Please confirm prescription.'
        };
    }

    /**
     * Bind all Phase 3 behaviour to a medication row element.
     * Compatible with multi-entry-manager row structure.
     *
     * @param {jQuery} $row       - the .multi-entry-row element
     * @param {object} [opts]     - { onQtyCalc, onPreviewUpdate, onHighDose }
     */
    function bindRow($row, opts) {
        opts = opts || {};

        var $drugSearch    = $row.find('.drug-search');
        var $drugId        = $row.find('.drug-id');
        var $doseInput     = $row.find('.med-dose');
        var $unitSel       = $row.find('.med-unit');
        var $strengthVal   = $row.find('.med-strength-value');
        var $strengthUnit  = $row.find('.med-strength-unit');
        var $freqSel       = $row.find('.med-freq');
        var $routeSel      = $row.find('.med-route');
        var $formSel       = $row.find('.med-form');
        var $daysInput     = $row.find('.med-days');
        var $qtyInput      = $row.find('.med-qty');
        var $qtyAuto       = $row.find('.med-qty-auto');
        var $activeMassEl  = $row.find('.mm-active-mass');
        var $reqUnitsHidden = $row.find('.mm-required-units');
        var $massValHidden  = $row.find('.mm-total-active-mass-value');
        var $massUnitHidden = $row.find('.mm-total-active-mass-unit');
        var $qtyOverride   = $row.find('.med-qty-override');
        var $previewBox    = $row.find('.med-preview');
        var $highDoseWarn  = $row.find('.med-high-dose-warn');
        var $nhisChk       = $row.find('.med-nhis-chk');
        var $freqLabel     = $row.find('input[name="frequency[]"]');  /* hidden: stores full label string for backward compat */

        /* Populate selects */
        loadMasters(function () {
            populateFreqSelect($freqSel);
            populateRouteSelect($routeSel);
            populateFormSelect($formSel);
            populateUnitSelect($unitSel, 'mg');
            if ($strengthUnit && $strengthUnit.length) {
                populateUnitSelect($strengthUnit, 'mg');
            }
        });

        /* ---- drug selection → auto-populate defaults ---- */
        $row.on('drug-selected', function (e, item) {
            if (!item) return;
            if (item.dosage_form)  $formSel.val(item.dosage_form);
            if (item.route)        $routeSel.val(item.route);
            if (item.standard_dosage && !$doseInput.val()) $doseInput.val(item.standard_dosage);
            if ($strengthVal.length && !$strengthVal.val()) {
                var parsed = _parseStrengthSimple(item.strength);
                if (parsed && parsed.value && !$strengthVal.val()) {
                    $strengthVal.val(parsed.value);
                }
                if (parsed && parsed.unit && $strengthUnit.length && !$strengthUnit.val()) {
                    $strengthUnit.val(parsed.unit);
                }
            }
            _triggerCalc($row, $qtyInput, $qtyAuto, $qtyOverride, $previewBox, $highDoseWarn, $freqLabel, opts);
        });

        /* ---- recalc triggers ---- */
        $doseInput.on('input change', function () {
            _triggerCalc($row, $qtyInput, $qtyAuto, $qtyOverride, $previewBox, $highDoseWarn, $freqLabel, opts);
        });
        $unitSel.on('change', function () {
            _triggerCalc($row, $qtyInput, $qtyAuto, $qtyOverride, $previewBox, $highDoseWarn, $freqLabel, opts);
        });
        $strengthVal.on('input change', function () {
            _triggerCalc($row, $qtyInput, $qtyAuto, $qtyOverride, $previewBox, $highDoseWarn, $freqLabel, opts);
        });
        $strengthUnit.on('change', function () {
            _triggerCalc($row, $qtyInput, $qtyAuto, $qtyOverride, $previewBox, $highDoseWarn, $freqLabel, opts);
        });
        $freqSel.on('change', function () {
            var code = $(this).val();
            var fEntry = FREQ_MAP[code];
            /* Sync hidden frequency field for backward-compat save */
            if ($freqLabel.length && fEntry) {
                $freqLabel.val(code + ' (' + fEntry.label + ')');
            } else if ($freqLabel.length) {
                $freqLabel.val(code);
            }
            _triggerCalc($row, $qtyInput, $qtyAuto, $qtyOverride, $previewBox, $highDoseWarn, $freqLabel, opts);
        });
        $daysInput.on('input change', function () {
            _triggerCalc($row, $qtyInput, $qtyAuto, $qtyOverride, $previewBox, $highDoseWarn, $freqLabel, opts);
        });
        $qtyOverride.on('change', function () {
            /* If override checked, unlock qty input; else lock to auto */
            if ($(this).is(':checked')) {
                $qtyInput.prop('readonly', false).focus();
            } else {
                $qtyInput.prop('readonly', true);
                _triggerCalc($row, $qtyInput, $qtyAuto, $qtyOverride, $previewBox, $highDoseWarn, $freqLabel, opts);
            }
        });
    }

    /* =========================================================================
     * PRIVATE HELPERS
     * ======================================================================= */

    function _triggerCalc($row, $qtyInput, $qtyAuto, $qtyOverride, $previewBox, $highDoseWarn, $freqLabel, opts) {
        var drugName  = $row.find('.drug-search').val()   || '';
        var dose      = $row.find('.med-dose').val()       || '';
        var unit      = $row.find('.med-unit').val()       || '';
        var strengthVal = $row.find('.med-strength-value').val() || '';
        var strengthUnit = $row.find('.med-strength-unit').val() || '';
        var freqCode  = $row.find('.med-freq').val()       || '';
        var form      = $row.find('.med-form').val()       || '';
        var route     = $row.find('.med-route').val()      || '';
        var days      = parseInt($row.find('.med-days').val()) || 0;
        var instruction = $row.find('.med-instruction').val() || '';

        var $activeMassEl  = $row.find('.mm-active-mass');
        var $reqUnitsHidden = $row.find('.mm-required-units');
        var $massValHidden  = $row.find('.mm-total-active-mass-value');
        var $massUnitHidden = $row.find('.mm-total-active-mass-unit');

        var totalActive = calculateTotalActiveMass(dose, freqCode, days);
        if (totalActive !== null) {
            if ($activeMassEl.length) {
                $activeMassEl.text('Total active ingredient: ' + totalActive.toFixed(2) + (unit ? (' ' + unit) : ''));
            }
            if ($massValHidden.length) $massValHidden.val(totalActive.toFixed(3));
            if ($massUnitHidden.length) $massUnitHidden.val(unit || '');
        } else {
            if ($activeMassEl.length) $activeMassEl.text('');
            if ($massValHidden.length) $massValHidden.val('');
            if ($massUnitHidden.length) $massUnitHidden.val(unit || '');
        }

        /* Auto-qty (only if override not checked) */
        var qty = null;
        if (!$qtyOverride.is(':checked')) {
            var doseNum = parseFloat(dose);
            if (_opts.structured_strength_enabled) {
                var ru = calculateRequiredUnits(doseNum, strengthVal, unit, strengthUnit, freqCode, days);
                if (ru !== null) {
                    qty = ru;
                    $qtyInput.val(ru.toFixed(2)).prop('readonly', true);
                    $qtyAuto.text('Required: ' + ru.toFixed(2) + (form ? (' ' + form.toLowerCase()) : ''));
                    if ($reqUnitsHidden.length) $reqUnitsHidden.val(ru.toFixed(3));
                } else {
                    qty = calculateQty(!isNaN(doseNum) ? doseNum : 1, freqCode, days);
                    if (qty !== null) {
                        $qtyInput.val(qty).prop('readonly', true);
                        $qtyAuto.text('Auto: ' + qty + (form ? ' ' + form.toLowerCase() + (qty !== 1 ? 's' : '') : ''));
                        if ($reqUnitsHidden.length) $reqUnitsHidden.val('');
                    } else {
                        $qtyInput.prop('readonly', false);
                        $qtyAuto.text('');
                        if ($reqUnitsHidden.length) $reqUnitsHidden.val('');
                    }
                }
            } else {
                qty = calculateQty(!isNaN(doseNum) ? doseNum : 1, freqCode, days);
                if (qty !== null) {
                    $qtyInput.val(qty).prop('readonly', true);
                    $qtyAuto.text('Auto: ' + qty + (form ? ' ' + form.toLowerCase() + (qty !== 1 ? 's' : '') : ''));
                } else {
                    $qtyInput.prop('readonly', false);
                    $qtyAuto.text('');
                }
            }
        }

        /* High-dose warning */
        var doseCheck = checkHighDose(drugName, dose, unit);
        if (doseCheck.high) {
            $highDoseWarn.html(
                '<i class="fa fa-exclamation-triangle"></i> <strong>Warning:</strong> ' +
                escapeHtml(doseCheck.message)
            ).show();
        } else {
            $highDoseWarn.hide().html('');
        }

        /* Live preview */
        var preview = buildPreview({
            drugName: drugName,
            dose: dose,
            unit: unit,
            freqCode: freqCode,
            form: form,
            route: route,
            days: days,
            qty: $qtyInput.val() || qty || '',
            instruction: instruction
        });
        if ($previewBox.length) {
            if (preview) {
                $previewBox.html(
                    '<pre style="margin:0;font-size:12px;white-space:pre-wrap;">' +
                    escapeHtml(preview) + '</pre>'
                ).show();
            } else {
                $previewBox.hide().html('');
            }
        }

        if (opts.onQtyCalc) opts.onQtyCalc(qty, $row);
        if (opts.onPreviewUpdate) opts.onPreviewUpdate(preview, $row);
        if (opts.onHighDose && doseCheck.high) opts.onHighDose(doseCheck, $row);
    }

    function _buildSelect($sel, masterArr, field, selected, fallback) {
        $sel.empty().append('<option value="">-- Select --</option>');
        var items = (masterArr && masterArr.length) ? masterArr : fallback;
        items.forEach(function (item) {
            var val   = (typeof item === 'object') ? (item[field] || item.code || '') : item;
            var label = (typeof item === 'object') ? (item[field] || item.code || '') : item;
            var $opt  = $('<option>').val(val).text(label);
            if (selected && selected === val) $opt.prop('selected', true);
            $sel.append($opt);
        });
    }

    function _injectStyles() {
        if ($('#med-modal-styles').length) return;
        var css = [
            '<style id="med-modal-styles">',
            /* ---- row layout ---- */
            '.mm-row { background:#fff; border:1px solid #e0e0e0; border-radius:8px; padding:14px 16px 10px; margin-bottom:12px; position:relative; box-shadow:0 1px 3px rgba(0,0,0,.06); }',
            '.mm-row:hover { border-color:#3c8dbc; box-shadow:0 2px 8px rgba(60,141,188,.12); }',
            '.mm-row .row-number { position:absolute; left:-10px; top:16px; width:26px; height:26px; background:#3c8dbc; color:#fff; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:700; }',
            '.mm-row .btn-remove-row { position:absolute; right:8px; top:8px; }',
            /* ---- section titles ---- */
            '.mm-section-title { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:#888; border-bottom:1px solid #eee; padding-bottom:3px; margin-bottom:8px; margin-top:6px; }',
            /* ---- dose row ---- */
            '.mm-dose-group { display:flex; gap:6px; align-items:flex-end; }',
            '.mm-dose-group .mm-dose-input { flex:0 0 80px; }',
            '.mm-dose-group .mm-unit-sel { flex:1; min-width:80px; }',
            /* ---- qty row ---- */
            '.mm-qty-row { display:flex; gap:6px; align-items:center; }',
            '.mm-qty-auto { font-size:11px; color:#27ae60; font-weight:600; white-space:nowrap; }',
            '.mm-qty-override-label { font-size:11px; color:#888; white-space:nowrap; cursor:pointer; }',
            /* ---- preview box ---- */
            '.mm-preview-box { background:#f0f8ff; border:1px solid #b8d4e8; border-radius:6px; padding:10px 12px; margin-top:8px; font-family:monospace; font-size:12px; color:#1a1a2e; display:none; }',
            '.mm-preview-label { font-size:10px; font-weight:700; text-transform:uppercase; color:#3c8dbc; margin-bottom:4px; letter-spacing:.5px; }',
            /* ---- high-dose warning ---- */
            '.mm-high-dose-warn { background:#fff3cd; border:1px solid #ffc107; border-radius:5px; padding:7px 10px; font-size:12px; color:#856404; margin-top:6px; display:none; }',
            /* ---- NHIS badge ---- */
            '.mm-nhis-badge { display:inline-block; background:#e8f5e9; border:1px solid #a5d6a7; border-radius:4px; padding:2px 7px; font-size:11px; color:#2e7d32; font-weight:600; margin-left:6px; }',
            /* ---- modal header gradient ---- */
            '.mm-modal-header { background:linear-gradient(135deg,#1a6985 0%,#0d9488 100%); color:#fff; border-radius:4px 4px 0 0; padding:14px 16px; }',
            '.mm-modal-header .close { color:#fff; opacity:.8; font-size:20px; }',
            '.mm-modal-header h4 { margin:0; font-size:16px; font-weight:600; }',
            /* ---- summary bar ---- */
            '.mm-summary-bar { background:linear-gradient(135deg,#0d9488 0%,#1a6985 100%); color:#fff; border-radius:6px; padding:10px 18px; margin-bottom:14px; display:flex; justify-content:space-between; align-items:center; }',
            '.mm-summary-bar .count { font-size:22px; font-weight:700; }',
            '.mm-summary-bar .label-text { font-size:11px; opacity:.9; }',
            '.mm-btn-add { background:linear-gradient(135deg,#0d9488 0%,#38ef7d 100%); border:none; color:#fff; padding:8px 18px; border-radius:20px; font-weight:600; transition:all .2s; }',
            '.mm-btn-add:hover { transform:translateY(-1px); box-shadow:0 4px 12px rgba(13,148,136,.4); color:#fff; }',
            '.mm-btn-save { background:linear-gradient(135deg,#1a6985 0%,#0d9488 100%); border:none; padding:10px 28px; font-size:14px; border-radius:20px; color:#fff; font-weight:600; }',
            '.mm-btn-save:hover { transform:translateY(-1px); box-shadow:0 4px 12px rgba(26,105,133,.4); color:#fff; }',
            '</style>'
        ].join('');
        $('head').append(css);
    }

    /* shared HTML escaper */
    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    /* Public surface */
    return {
        init:               init,
        loadMasters:        loadMasters,
        populateRouteSelect: populateRouteSelect,
        populateFormSelect:  populateFormSelect,
        populateUnitSelect:  populateUnitSelect,
        populateFreqSelect:  populateFreqSelect,
        calculateQty:       calculateQty,
        buildPreview:       buildPreview,
        checkHighDose:      checkHighDose,
        bindRow:            bindRow,
        FREQ_MAP:           FREQ_MAP,
        escapeHtml:         escapeHtml
    };

}());
