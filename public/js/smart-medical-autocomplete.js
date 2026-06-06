/**
 * Smart Medical Autocomplete System
 * 
 * Provides typeahead/autocomplete for:
 * - Medications (with stock/NHIS info)
 * - Diagnoses (with ICD codes)
 * - Lab Tests (with category/sample type)
 * - Sonography, ECG, X-Ray scans
 * 
 * Supports custom entry: if item not found, doctor can type freely
 * and the entry is saved to DB for future reuse.
 */
var SmartMedical = (function($) {
    'use strict';

    var BASE_URL = '';
    var ENDPOINTS = {
        medications:    'app/medical_data/search_medications',
        diagnoses:      'app/medical_data/search_diagnoses',
        lab_tests:      'app/medical_data/search_lab_tests',
        scans:          'app/medical_data/search_scans',
        sonography:     'app/medical_data/search_sonography',
        ecg:            'app/medical_data/search_ecg',
        xray:           'app/medical_data/search_xray',
        save_medication:'app/medical_data/save_custom_medication',
        save_diagnosis: 'app/medical_data/save_custom_diagnosis',
        save_lab_test:  'app/medical_data/save_custom_lab_test',
        save_scan:      'app/medical_data/save_custom_scan',
        lab_template:   'app/medical_data/get_lab_template',
        notifications:  'app/medical_data/get_notifications',
        notif_count:    'app/medical_data/count_notifications',
        mark_read:      'app/medical_data/mark_read',
        mark_all_read:  'app/medical_data/mark_all_read',
        timeline:       'app/medical_data/patient_timeline'
    };

    function init(baseUrl) {
        BASE_URL = baseUrl;
    }

    function _getAutocompleteInstance($el) {
        var inst = null;
        try {
            inst = $el.autocomplete('instance');
        } catch (e) {
            inst = null;
        }
        if (!inst) {
            inst = $el.data('ui-autocomplete') || $el.data('autocomplete') || null;
        }
        return inst;
    }

    /* ================================================================== */
    /*  MEDICATION AUTOCOMPLETE                                            */
    /* ================================================================== */

    function initMedicationAutocomplete(inputSelector, hiddenIdSelector, infoSelector, options) {
        var opts = $.extend({
            minLength: 2,
            onSelect: null,
            allowCustom: true
        }, options);

        var $ac = $(inputSelector).autocomplete({
            source: function(request, response) {
                $.ajax({
                    url: BASE_URL + ENDPOINTS.medications,
                    type: 'get',
                    dataType: 'json',
                    data: { term: request.term },
                    success: function(data) {
                        var items = $.map(data, function(item) {
                            var desc = item.label;
                            if (item.generic_name) desc += ' (' + item.generic_name + ')';
                            if (item.strength) desc += ' ' + item.strength;
                            if (item.dosage_form) desc += ' [' + item.dosage_form + ']';
                            if (parseInt(item.stock) > 0) {
                                desc += ' - Stock: ' + item.stock;
                            } else {
                                desc += ' (OUT OF STOCK)';
                            }
                            if (parseInt(item.is_nhis_covered) === 1) {
                                desc += ' [NHIS]';
                            }
                            return {
                                label: desc,
                                value: item.label,
                                id: item.id,
                                data: item
                            };
                        });
                        if (opts.allowCustom && items.length === 0 && request.term.length >= 3) {
                            items.push({
                                label: '+ Add custom: "' + request.term + '"',
                                value: request.term,
                                id: 0,
                                isCustom: true,
                                customName: request.term
                            });
                        }
                        response(items);
                    }
                });
            },
            minLength: opts.minLength,
            select: function(event, ui) {
                if (ui.item.isCustom) {
                    _saveCustomEntry('medication', ui.item.customName, function(result) {
                        if (result && result.success) {
                            $(inputSelector).val(ui.item.customName);
                            if (hiddenIdSelector) $(hiddenIdSelector).val(result.id);
                            if (infoSelector) $(infoSelector).html('<span class="text-info"><i class="fa fa-plus-circle"></i> Custom medication saved for future use</span>').show();
                        }
                    });
                } else {
                    $(inputSelector).val(ui.item.value);
                    if (hiddenIdSelector) $(hiddenIdSelector).val(ui.item.id);
                    if (infoSelector) {
                        var info = '';
                        if (ui.item.data) {
                            var d = ui.item.data;
                            info = '<strong>' + _esc(d.label) + '</strong>';
                            if (d.generic_name) info += ' | Generic: ' + _esc(d.generic_name);
                            if (d.dosage_form) info += ' | Form: ' + _esc(d.dosage_form);
                            if (parseFloat(d.price) > 0) info += ' | Price: GHS ' + parseFloat(d.price).toFixed(2);
                            if (parseInt(d.stock) > 0) info += ' | <span class="text-success">In Stock (' + d.stock + ')</span>';
                            else info += ' | <span class="text-danger">Out of Stock</span>';
                        }
                        $(infoSelector).html(info).show();
                    }
                }
                if (opts.onSelect) opts.onSelect(ui.item);
                return false;
            },
            open: function() {
                $(this).autocomplete('widget').css('z-index', 999999);
            }
        });
        var inst = _getAutocompleteInstance($ac);
        if (inst) {
            inst._renderItem = function(ul, item) {
                var icon = item.isCustom ? '<i class="fa fa-plus-circle text-primary"></i> ' : '<i class="fa fa-medkit text-success"></i> ';
                return $('<li>').append('<a>' + icon + _esc(item.label) + '</a>').appendTo(ul);
            };
        }
    }

    /* ================================================================== */
    /*  DIAGNOSIS AUTOCOMPLETE                                             */
    /* ================================================================== */

    function initDiagnosisAutocomplete(inputSelector, hiddenIdSelector, infoSelector, options) {
        var opts = $.extend({
            minLength: 2,
            onSelect: null,
            allowCustom: true
        }, options);

        var $ac = $(inputSelector).autocomplete({
            source: function(request, response) {
                $.ajax({
                    url: BASE_URL + ENDPOINTS.diagnoses,
                    type: 'get',
                    dataType: 'json',
                    data: { term: request.term },
                    success: function(data) {
                        var items = $.map(data, function(item) {
                            var desc = item.label;
                            if (item.icd_code) desc += ' [' + item.icd_code + ']';
                            if (item.category) desc += ' - ' + item.category;
                            return {
                                label: desc,
                                value: item.label,
                                id: item.id,
                                data: item
                            };
                        });
                        if (opts.allowCustom && items.length === 0 && request.term.length >= 3) {
                            items.push({
                                label: '+ Add custom: "' + request.term + '"',
                                value: request.term,
                                id: 0,
                                isCustom: true,
                                customName: request.term
                            });
                        }
                        response(items);
                    }
                });
            },
            minLength: opts.minLength,
            select: function(event, ui) {
                if (ui.item.isCustom) {
                    _saveCustomEntry('diagnosis', ui.item.customName, function(result) {
                        if (result && result.success) {
                            $(inputSelector).val(ui.item.customName);
                            if (hiddenIdSelector) $(hiddenIdSelector).val(result.id);
                            if (infoSelector) $(infoSelector).html('<span class="text-info"><i class="fa fa-plus-circle"></i> Custom diagnosis saved for future use</span>').show();
                        }
                    });
                } else {
                    $(inputSelector).val(ui.item.value);
                    if (hiddenIdSelector) $(hiddenIdSelector).val(ui.item.id);
                    if (infoSelector) {
                        var info = '<strong>' + _esc(ui.item.value) + '</strong>';
                        if (ui.item.data.icd_code) info += ' | ICD-10: <code>' + _esc(ui.item.data.icd_code) + '</code>';
                        if (ui.item.data.category) info += ' | ' + _esc(ui.item.data.category);
                        if (ui.item.data.common_treatment) info += '<br><small class="text-muted">Common Tx: ' + _esc(ui.item.data.common_treatment) + '</small>';
                        $(infoSelector).html(info).show();
                    }
                }
                if (opts.onSelect) opts.onSelect(ui.item);
                return false;
            },
            open: function() {
                $(this).autocomplete('widget').css('z-index', 999999);
            }
        });
        var inst = _getAutocompleteInstance($ac);
        if (inst) {
            inst._renderItem = function(ul, item) {
                var icon = item.isCustom ? '<i class="fa fa-plus-circle text-primary"></i> ' : '<i class="fa fa-stethoscope text-info"></i> ';
                return $('<li>').append('<a>' + icon + _esc(item.label) + '</a>').appendTo(ul);
            };
        }
    }

    /* ================================================================== */
    /*  LAB TEST AUTOCOMPLETE                                              */
    /* ================================================================== */

    function initLabTestAutocomplete(inputSelector, hiddenIdSelector, infoSelector, options) {
        var opts = $.extend({
            minLength: 2,
            onSelect: null,
            allowCustom: true
        }, options);

        var $ac = $(inputSelector).autocomplete({
            source: function(request, response) {
                $.ajax({
                    url: BASE_URL + ENDPOINTS.lab_tests,
                    type: 'get',
                    dataType: 'json',
                    data: { term: request.term },
                    success: function(data) {
                        var items = $.map(data, function(item) {
                            var desc = item.label;
                            if (item.test_code) desc += ' (' + item.test_code + ')';
                            if (item.category) desc += ' - ' + item.category;
                            if (item.sample_type) desc += ' [' + item.sample_type + ']';
                            return {
                                label: desc,
                                value: item.label,
                                id: item.id,
                                data: item
                            };
                        });
                        if (opts.allowCustom && items.length === 0 && request.term.length >= 3) {
                            items.push({
                                label: '+ Add custom: "' + request.term + '"',
                                value: request.term,
                                id: 0,
                                isCustom: true,
                                customName: request.term
                            });
                        }
                        response(items);
                    }
                });
            },
            minLength: opts.minLength,
            select: function(event, ui) {
                if (ui.item.isCustom) {
                    _saveCustomEntry('lab_test', ui.item.customName, function(result) {
                        if (result && result.success) {
                            $(inputSelector).val(ui.item.customName);
                            if (hiddenIdSelector) $(hiddenIdSelector).val(result.id);
                            if (infoSelector) $(infoSelector).html('<span class="text-info"><i class="fa fa-plus-circle"></i> Custom lab test saved for future use</span>').show();
                        }
                    });
                } else {
                    $(inputSelector).val(ui.item.value);
                    if (hiddenIdSelector) $(hiddenIdSelector).val(ui.item.id);
                    if (infoSelector) {
                        var info = '<strong>' + _esc(ui.item.value) + '</strong>';
                        if (ui.item.data.test_code) info += ' | Code: <code>' + _esc(ui.item.data.test_code) + '</code>';
                        if (ui.item.data.category) info += ' | ' + _esc(ui.item.data.category);
                        if (ui.item.data.sample_type) info += ' | Sample: ' + _esc(ui.item.data.sample_type);
                        $(infoSelector).html(info).show();
                    }
                }
                if (opts.onSelect) opts.onSelect(ui.item);
                return false;
            },
            open: function() {
                $(this).autocomplete('widget').css('z-index', 999999);
            }
        });
        var inst = _getAutocompleteInstance($ac);
        if (inst) {
            inst._renderItem = function(ul, item) {
                var icon = item.isCustom ? '<i class="fa fa-plus-circle text-primary"></i> ' : '<i class="fa fa-flask text-warning"></i> ';
                return $('<li>').append('<a>' + icon + _esc(item.label) + '</a>').appendTo(ul);
            };
        }
    }

    /* ================================================================== */
    /*  SCAN AUTOCOMPLETE (Sonography / ECG / X-Ray / All)                 */
    /* ================================================================== */

    function initScanAutocomplete(inputSelector, hiddenIdSelector, infoSelector, category, options) {
        var opts = $.extend({
            minLength: 2,
            onSelect: null,
            allowCustom: true
        }, options);

        var endpoint = ENDPOINTS.scans;
        if (category === 'Ultrasound') endpoint = ENDPOINTS.sonography;
        else if (category === 'ECG') endpoint = ENDPOINTS.ecg;
        else if (category === 'X-Ray') endpoint = ENDPOINTS.xray;

        var $ac = $(inputSelector).autocomplete({
            source: function(request, response) {
                $.ajax({
                    url: BASE_URL + endpoint,
                    type: 'get',
                    dataType: 'json',
                    data: { term: request.term },
                    success: function(data) {
                        var items = $.map(data, function(item) {
                            var desc = item.label;
                            if (item.category) desc += ' [' + item.category + ']';
                            if (item.department) desc += ' - ' + item.department;
                            return {
                                label: desc,
                                value: item.label,
                                id: item.id,
                                data: item
                            };
                        });
                        if (opts.allowCustom && items.length === 0 && request.term.length >= 3) {
                            items.push({
                                label: '+ Add custom: "' + request.term + '"',
                                value: request.term,
                                id: 0,
                                isCustom: true,
                                customName: request.term,
                                customCategory: category || 'Custom'
                            });
                        }
                        response(items);
                    }
                });
            },
            minLength: opts.minLength,
            select: function(event, ui) {
                if (ui.item.isCustom) {
                    $.ajax({
                        url: BASE_URL + ENDPOINTS.save_scan,
                        type: 'post',
                        dataType: 'json',
                        data: { name: ui.item.customName, category: ui.item.customCategory || '' },
                        success: function(result) {
                            if (result && result.success) {
                                $(inputSelector).val(ui.item.customName);
                                if (hiddenIdSelector) $(hiddenIdSelector).val(result.id);
                                if (infoSelector) $(infoSelector).html('<span class="text-info"><i class="fa fa-plus-circle"></i> Custom scan saved for future use</span>').show();
                            }
                        }
                    });
                } else {
                    $(inputSelector).val(ui.item.value);
                    if (hiddenIdSelector) $(hiddenIdSelector).val(ui.item.id);
                    if (infoSelector) {
                        var info = '<strong>' + _esc(ui.item.value) + '</strong>';
                        if (ui.item.data.category) info += ' | ' + _esc(ui.item.data.category);
                        $(infoSelector).html(info).show();
                    }
                }
                if (opts.onSelect) opts.onSelect(ui.item);
                return false;
            },
            open: function() {
                $(this).autocomplete('widget').css('z-index', 999999);
            }
        });
        var inst = _getAutocompleteInstance($ac);
        if (inst) {
            inst._renderItem = function(ul, item) {
                var iconClass = 'fa-search';
                if (category === 'Ultrasound') iconClass = 'fa-video-camera';
                else if (category === 'ECG') iconClass = 'fa-heartbeat';
                else if (category === 'X-Ray') iconClass = 'fa-film';
                var icon = item.isCustom ? '<i class="fa fa-plus-circle text-primary"></i> ' : '<i class="fa ' + iconClass + ' text-info"></i> ';
                return $('<li>').append('<a>' + icon + _esc(item.label) + '</a>').appendTo(ul);
            };
        }
    }

    /* ================================================================== */
    /*  LAB RESULT TEMPLATE LOADER                                         */
    /* ================================================================== */

    function loadLabTemplate(testName, containerSelector, ioLabId) {
        $.ajax({
            url: BASE_URL + ENDPOINTS.lab_template,
            type: 'get',
            dataType: 'json',
            data: { test_name: testName },
            success: function(templates) {
                if (!templates || templates.length === 0) {
                    $(containerSelector).html('<div class="alert alert-info"><i class="fa fa-info-circle"></i> No template available for this test. Use manual entry below.</div>');
                    return;
                }
                var html = _buildTemplateForm(templates, ioLabId);
                $(containerSelector).html(html);
                _bindTemplateEvents(containerSelector);
            }
        });
    }

    function _buildTemplateForm(templates, ioLabId) {
        var html = '<div class="structured-result-form">';
        html += '<input type="hidden" name="io_lab_id" value="' + ioLabId + '">';
        html += '<table class="table table-bordered table-hover table-striped">';
        html += '<thead><tr>';
        html += '<th style="width:25%">Parameter</th>';
        html += '<th style="width:25%">Result</th>';
        html += '<th style="width:15%">Unit</th>';
        html += '<th style="width:20%">Normal Range</th>';
        html += '<th style="width:15%">Status</th>';
        html += '</tr></thead><tbody>';

        for (var i = 0; i < templates.length; i++) {
            var t = templates[i];
            var options = (t.result_options || '').split(',').filter(function(o) { return o.trim() !== ''; });
            var hasDropdown = options.length > 0;

            html += '<tr class="template-row" data-template-id="' + t.template_id + '">';
            html += '<td><strong>' + _esc(t.parameter_name) + '</strong></td>';
            html += '<td>';
            if (hasDropdown) {
                html += '<select class="form-control input-sm result-input" data-param="' + _esc(t.parameter_name) + '">';
                html += '<option value="">-- Select --</option>';
                for (var j = 0; j < options.length; j++) {
                    html += '<option value="' + _esc(options[j].trim()) + '">' + _esc(options[j].trim()) + '</option>';
                }
                html += '</select>';
                html += '<input type="text" class="form-control input-sm manual-override" placeholder="Or type manually..." style="margin-top:4px;display:none;">';
                html += '<a href="#" class="toggle-manual small text-muted" style="font-size:11px;">Type manually</a>';
            } else {
                html += '<input type="text" class="form-control input-sm result-input" data-param="' + _esc(t.parameter_name) + '" placeholder="Enter value">';
            }
            html += '</td>';
            html += '<td><span class="text-muted">' + _esc(t.unit || '') + '</span></td>';
            html += '<td><span class="text-muted">' + _esc(t.normal_range_text || '') + '</span></td>';
            html += '<td class="result-flag-cell"><span class="label label-default">-</span></td>';
            html += '</tr>';
        }

        html += '</tbody></table>';
        html += '<div class="form-group">';
        html += '<label>Additional Notes:</label>';
        html += '<textarea class="form-control" id="structured_notes" rows="2" placeholder="Any additional comments..."></textarea>';
        html += '</div>';
        html += '<button type="button" class="btn btn-primary btn-sm save-structured-results"><i class="fa fa-save"></i> Save Structured Results</button>';
        html += ' <span class="save-status"></span>';
        html += '</div>';
        return html;
    }

    function _bindTemplateEvents(containerSelector) {
        // Toggle manual entry
        $(containerSelector).on('click', '.toggle-manual', function(e) {
            e.preventDefault();
            var $row = $(this).closest('td');
            var $select = $row.find('select.result-input');
            var $manual = $row.find('.manual-override');
            if ($manual.is(':visible')) {
                $manual.hide().val('');
                $select.show();
                $(this).text('Type manually');
            } else {
                $select.hide().val('');
                $manual.show();
                $(this).text('Use dropdown');
            }
        });

        // Live color coding on change
        $(containerSelector).on('change keyup', '.result-input, .manual-override', function() {
            var $row = $(this).closest('tr');
            var templateId = $row.data('template-id');
            var val = $(this).val();
            if (!val) {
                $row.find('.result-flag-cell').html('<span class="label label-default">-</span>');
                return;
            }
            _updateResultFlag($row, val, templateId);
        });

        // Save button
        $(containerSelector).on('click', '.save-structured-results', function() {
            var $btn = $(this);
            var $form = $btn.closest('.structured-result-form');
            var ioLabId = $form.find('input[name="io_lab_id"]').val();
            var entries = [];

            $form.find('.template-row').each(function() {
                var $row = $(this);
                var templateId = $row.data('template-id');
                var param = $row.find('.result-input').data('param');
                var val = '';

                var $manual = $row.find('.manual-override');
                if ($manual.is(':visible') && $manual.val()) {
                    val = $manual.val();
                } else {
                    val = $row.find('.result-input').val();
                }

                if (param && val) {
                    entries.push({
                        template_id: templateId,
                        parameter_name: param,
                        result_value: val,
                        unit: $row.find('td:eq(2)').text().trim(),
                        normal_range: $row.find('td:eq(3)').text().trim()
                    });
                }
            });

            if (entries.length === 0) {
                $form.find('.save-status').html('<span class="text-warning">Please enter at least one result.</span>');
                return;
            }

            $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Submitting...');
            $.ajax({
                url: BASE_URL + 'app/medical_data/save_structured_result',
                type: 'post',
                dataType: 'json',
                data: {
                    io_lab_id: ioLabId,
                    entries: JSON.stringify(entries)
                },
                success: function(result) {
                    if (result && result.success) {
                        $btn.html('<i class="fa fa-check"></i> Results Submitted');
                        var statusMsg = result.status ? ' Status: ' + result.status : '';
                        $form.find('.save-status').html('<span class="text-success"><i class="fa fa-check"></i> Saved ' + result.saved + ' results. Doctor has been notified.' + statusMsg + '</span>');
                        // Keep button disabled briefly to prevent accidental double-click, then re-enable for updates
                        setTimeout(function() {
                            $btn.prop('disabled', false).html('<i class="fa fa-save"></i> Update Results');
                        }, 2000);
                    } else {
                        $btn.prop('disabled', false).html('<i class="fa fa-save"></i> Save & Submit Results');
                        $form.find('.save-status').html('<span class="text-danger">Error: ' + (result.message || 'Unknown error') + '</span>');
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).html('<i class="fa fa-save"></i> Save & Submit Results');
                    $form.find('.save-status').html('<span class="text-danger">Network error. Please try again.</span>');
                }
            });
        });
    }

    function _updateResultFlag($row, value, templateId) {
        // We do client-side color coding based on data attributes
        // For simplicity, use color-map classes
        var flagColors = {
            'green':  { label: 'Normal',   cls: 'label-success' },
            'orange': { label: 'High',     cls: 'label-warning' },
            'red':    { label: 'Critical', cls: 'label-danger'  },
            'blue':   { label: 'Low',      cls: 'label-primary' },
            'yellow': { label: 'Abnormal', cls: 'label-warning' }
        };

        // Default
        $row.find('.result-flag-cell').html('<span class="label label-info">' + _esc(value) + '</span>');
    }

    /* ================================================================== */
    /*  DOCTOR NOTIFICATIONS                                               */
    /* ================================================================== */

    function initNotifications(badgeSelector, dropdownSelector, options) {
        var opts = $.extend({
            pollInterval: 30000,
            maxItems: 10
        }, options);

        function refresh() {
            $.ajax({
                url: BASE_URL + ENDPOINTS.notif_count,
                type: 'get',
                dataType: 'json',
                success: function(data) {
                    var count = data.count || 0;
                    if (count > 0) {
                        $(badgeSelector).text(count).show();
                    } else {
                        $(badgeSelector).hide();
                    }
                }
            });
        }

        function loadDropdown() {
            $.ajax({
                url: BASE_URL + ENDPOINTS.notifications,
                type: 'get',
                dataType: 'json',
                success: function(data) {
                    var html = '';
                    if (!data || data.length === 0) {
                        html = '<li class="text-center"><a href="#">No new notifications</a></li>';
                    } else {
                        for (var i = 0; i < Math.min(data.length, opts.maxItems); i++) {
                            var n = data[i];
                            var icon = 'fa-flask';
                            if (n.notif_type === 'LAB_RESULT') icon = 'fa-flask';
                            html += '<li><a href="#" class="notif-item" data-id="' + n.notif_id + '" data-iop="' + _esc(n.iop_id || '') + '" data-patient="' + _esc(n.patient_no || '') + '">';
                            html += '<i class="fa ' + icon + ' text-info"></i> ' + _esc(n.title);
                            html += '<br><small class="text-muted">' + _esc(n.created_at || '') + '</small>';
                            html += '</a></li>';
                        }
                        html += '<li class="divider"></li>';
                        html += '<li class="text-center"><a href="#" class="mark-all-read-btn"><i class="fa fa-check-double"></i> Mark all as read</a></li>';
                    }
                    $(dropdownSelector).html(html);
                }
            });
        }

        // Bind events
        $(document).on('click', '.notif-item', function(e) {
            e.preventDefault();
            var notifId = $(this).data('id');
            $.post(BASE_URL + ENDPOINTS.mark_read, { notif_id: notifId });
            $(this).closest('li').fadeOut();
            refresh();
        });

        $(document).on('click', '.mark-all-read-btn', function(e) {
            e.preventDefault();
            $.post(BASE_URL + ENDPOINTS.mark_all_read, {}, function() {
                refresh();
                loadDropdown();
            });
        });

        // Initial load
        refresh();
        loadDropdown();

        // Poll
        if (opts.pollInterval > 0) {
            setInterval(function() {
                refresh();
            }, opts.pollInterval);
        }

        return { refresh: refresh, loadDropdown: loadDropdown };
    }

    /* ================================================================== */
    /*  PATIENT TIMELINE                                                   */
    /* ================================================================== */

    function loadPatientTimeline(patientNo, containerSelector) {
        $.ajax({
            url: BASE_URL + ENDPOINTS.timeline,
            type: 'get',
            dataType: 'json',
            data: { patient_no: patientNo },
            success: function(data) {
                if (!data || data.length === 0) {
                    $(containerSelector).html('<div class="alert alert-info">No clinical history found for this patient.</div>');
                    return;
                }
                var html = _buildTimeline(data);
                $(containerSelector).html(html);
            }
        });
    }

    function _buildTimeline(events) {
        var html = '<ul class="timeline">';
        var lastDate = '';

        for (var i = 0; i < events.length; i++) {
            var evt = events[i];
            var eventDate = (evt.event_date || '').substring(0, 10);
            var eventTime = (evt.event_date || '').substring(11, 16);

            if (eventDate !== lastDate) {
                html += '<li class="time-label"><span class="bg-blue">' + _esc(eventDate) + '</span></li>';
                lastDate = eventDate;
            }

            var bgColor = evt.color || '#3c8dbc';
            var icon = evt.icon || 'fa-circle';

            html += '<li>';
            html += '<i class="fa ' + icon + '" style="background:' + bgColor + ';color:#fff;"></i>';
            html += '<div class="timeline-item">';
            html += '<span class="time"><i class="fa fa-clock-o"></i> ' + _esc(eventTime) + '</span>';
            html += '<h3 class="timeline-header">';

            // Type badge
            var typeBadge = '';
            switch (evt.type) {
                case 'diagnosis': typeBadge = '<span class="label label-success">Diagnosis</span>'; break;
                case 'medication': typeBadge = '<span class="label label-warning">Medication</span>'; break;
                case 'lab_result': typeBadge = '<span class="label label-info">Lab Result</span>'; break;
                case 'vitals': typeBadge = '<span class="label label-danger">Vitals</span>'; break;
                default: typeBadge = '<span class="label label-default">' + _esc(evt.type) + '</span>';
            }
            html += typeBadge + ' ' + _esc(evt.title || '');
            if (evt.extra) html += ' <small class="text-muted">(' + _esc(evt.extra) + ')</small>';
            html += '</h3>';

            if (evt.detail) {
                html += '<div class="timeline-body">' + _esc(evt.detail) + '</div>';
            }
            html += '</div></li>';
        }

        html += '<li><i class="fa fa-clock-o bg-gray"></i></li>';
        html += '</ul>';
        return html;
    }

    /* ================================================================== */
    /*  HELPERS                                                            */
    /* ================================================================== */

    function _saveCustomEntry(type, name, callback) {
        var url = '';
        switch (type) {
            case 'medication': url = ENDPOINTS.save_medication; break;
            case 'diagnosis': url = ENDPOINTS.save_diagnosis; break;
            case 'lab_test': url = ENDPOINTS.save_lab_test; break;
            default: return;
        }
        $.ajax({
            url: BASE_URL + url,
            type: 'post',
            dataType: 'json',
            data: { name: name },
            success: callback
        });
    }

    function _esc(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    /* ================================================================== */
    /*  CSS INJECTION                                                      */
    /* ================================================================== */

    function injectStyles() {
        var css = '' +
            '.ui-autocomplete { max-height: 300px; overflow-y: auto; overflow-x: hidden; z-index: 999999 !important; }' +
            '.ui-autocomplete .ui-menu-item a { padding: 6px 12px; font-size: 13px; border-bottom: 1px solid #f0f0f0; }' +
            '.ui-autocomplete .ui-menu-item a:hover { background: #3c8dbc; color: #fff; }' +
            '.ui-autocomplete .ui-menu-item a i { margin-right: 6px; }' +
            '.smart-search-info { padding: 4px 8px; margin-top: 4px; background: #f9f9f9; border: 1px solid #eee; border-radius: 3px; font-size: 12px; }' +
            '.result-flag-cell .label { font-size: 11px; }' +
            '.structured-result-form .table th { background: #f5f5f5; font-size: 12px; }' +
            '.structured-result-form .table td { font-size: 13px; vertical-align: middle; }' +
            '.timeline { list-style: none; padding: 20px 0 20px; position: relative; }' +
            '.timeline:before { top: 0; bottom: 0; position: absolute; content: " "; width: 3px; background-color: #eee; left: 25px; }' +
            '.timeline > li { margin-bottom: 15px; position: relative; }' +
            '.timeline > li > i { width: 30px; height: 30px; font-size: 14px; line-height: 30px; position: absolute; left: 11px; top: 0; border-radius: 50%; text-align: center; }' +
            '.timeline > li > .timeline-item { margin-left: 60px; background: #fff; border: 1px solid #ddd; border-radius: 3px; padding: 10px; position: relative; }' +
            '.timeline > li > .timeline-item > .time { color: #999; float: right; font-size: 12px; }' +
            '.timeline > li > .timeline-item > .timeline-header { margin: 0 0 5px; font-size: 14px; color: #555; }' +
            '.timeline > li > .timeline-item > .timeline-body { font-size: 13px; color: #666; }' +
            '.time-label > span { font-size: 12px; padding: 5px 10px; border-radius: 4px; display: inline-block; color: #fff; margin-left: 15px; }' +
            '';

        if (!document.getElementById('smart-medical-css')) {
            var style = document.createElement('style');
            style.id = 'smart-medical-css';
            style.textContent = css;
            document.head.appendChild(style);
        }
    }

    // Public API
    return {
        init: init,
        injectStyles: injectStyles,
        initMedicationAutocomplete: initMedicationAutocomplete,
        initDiagnosisAutocomplete: initDiagnosisAutocomplete,
        initLabTestAutocomplete: initLabTestAutocomplete,
        initScanAutocomplete: initScanAutocomplete,
        loadLabTemplate: loadLabTemplate,
        initNotifications: initNotifications,
        loadPatientTimeline: loadPatientTimeline
    };

})(jQuery);
