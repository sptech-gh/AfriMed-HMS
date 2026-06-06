/**
 * Multi-Entry Manager v2.0 - Fixed for instance isolation
 * Modern multi-item entry system for HMS clinical modules
 * Supports: Diagnosis, Medication, Complaints, Laboratory, Sonography, Radiology
 */
function MultiEntryManager() {
    'use strict';
    
    this.baseUrl = '';
    this.entries = [];
    this.config = {};
    this.rowCounter = 0;
}

MultiEntryManager.prototype.init = function(base, options) {
    this.baseUrl = base;
    this.config = $.extend({
        module: 'diagnosis',
        containerId: 'multi-entry-container',
        countId: 'entry-count',
        saveButtonId: 'btn-save-all',
        maxEntries: 20,
        onEntryAdded: null,
        onEntryRemoved: null,
        onSaveSuccess: null,
        onSaveError: null
    }, options);
    this.entries = [];
    this.rowCounter = 0;
    this.injectStyles();
};

MultiEntryManager.prototype.injectStyles = function() {
    if ($('#multi-entry-styles').length) return;
    var css = `
        <style id="multi-entry-styles">
            .multi-entry-modal .modal-dialog { max-width: 900px; width: 95%; }
            .multi-entry-modal .modal-body { max-height: 70vh; overflow-y: auto; padding: 15px; }
            .multi-entry-row { 
                background: #f9f9f9; 
                border: 1px solid #e0e0e0; 
                border-radius: 6px; 
                padding: 12px 15px; 
                margin-bottom: 10px;
                position: relative;
                transition: all 0.2s ease;
            }
            .multi-entry-row:hover { 
                background: #f5f5f5; 
                border-color: #3c8dbc;
                box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            }
            .multi-entry-row .row-number {
                position: absolute;
                left: -8px;
                top: 50%;
                transform: translateY(-50%);
                width: 24px;
                height: 24px;
                background: #3c8dbc;
                color: #fff;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 11px;
                font-weight: bold;
            }
            .multi-entry-row .btn-remove-row {
                position: absolute;
                right: 8px;
                top: 8px;
                padding: 2px 8px;
                font-size: 14px;
                opacity: 0.6;
            }
            .multi-entry-row .btn-remove-row:hover { opacity: 1; }
            .multi-entry-row .form-group { margin-bottom: 8px; }
            .multi-entry-row label { font-size: 11px; color: #666; margin-bottom: 2px; }
            .multi-entry-row .form-control { font-size: 13px; }
            .multi-entry-row .form-control-sm { height: 30px; padding: 4px 8px; }
            .multi-entry-row textarea.form-control { min-height: 50px; }
            .multi-entry-summary {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: #fff;
                border-radius: 6px;
                padding: 12px 20px;
                margin-bottom: 15px;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            .multi-entry-summary .count { font-size: 24px; font-weight: bold; }
            .multi-entry-summary .label-text { font-size: 12px; opacity: 0.9; }
            .btn-add-entry {
                background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
                border: none;
                color: #fff;
                padding: 10px 20px;
                border-radius: 25px;
                font-weight: 500;
                transition: all 0.3s ease;
            }
            .btn-add-entry:hover { 
                transform: translateY(-2px); 
                box-shadow: 0 4px 12px rgba(17,153,142,0.4);
                color: #fff;
            }
            .btn-save-all {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                border: none;
                padding: 12px 30px;
                font-size: 15px;
                border-radius: 25px;
            }
            .btn-save-all:hover { 
                transform: translateY(-2px); 
                box-shadow: 0 4px 12px rgba(102,126,234,0.4);
            }
            .empty-state {
                text-align: center;
                padding: 40px 20px;
                color: #999;
            }
            .empty-state i { font-size: 48px; margin-bottom: 15px; opacity: 0.5; }
            .empty-state p { font-size: 14px; }
            .entry-badge {
                display: inline-block;
                padding: 3px 8px;
                border-radius: 12px;
                font-size: 11px;
                font-weight: 500;
                margin-right: 5px;
            }
            .entry-badge-primary { background: #e3f2fd; color: #1976d2; }
            .entry-badge-success { background: #e8f5e9; color: #388e3c; }
            .entry-badge-info { background: #e0f7fa; color: #0097a7; }
            .saving-overlay {
                position: absolute;
                top: 0; left: 0; right: 0; bottom: 0;
                background: rgba(255,255,255,0.9);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 1000;
                border-radius: 6px;
            }
            .saving-overlay i { font-size: 24px; color: #3c8dbc; }
        </style>
    `;
    $('head').append(css);
};

MultiEntryManager.prototype.escapeHtml = function(text) {
    if (!text) return '';
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(text));
    return div.innerHTML;
};

MultiEntryManager.prototype.addEntry = function(type, data) {
    var self = this;
    var $container = $('#' + self.config.containerId);
    var rowHtml = '';
    
    self.rowCounter++;
    var rowNum = self.rowCounter;

    try {
        switch(type) {
            case 'diagnosis':
                rowHtml = self.createDiagnosisRow(data.diagnosisList, rowNum);
                break;
            case 'complaint':
                rowHtml = self.createComplaintRow(rowNum);
                break;
            case 'laboratory':
                rowHtml = self.createLaboratoryRow(data.categories, data.tests, rowNum);
                break;
            case 'medication':
                rowHtml = self.createMedicationRow(data.categories, rowNum);
                break;
            case 'sonography':
                rowHtml = self.createSonographyRow(data.items, rowNum);
                break;
            case 'radiology':
                rowHtml = self.createRadiologyRow(data.tests, rowNum);
                break;
        }
    } catch (e) {
        console.error('Error creating row for type ' + type + ':', e);
        return;
    }

    $container.find('.empty-state').remove();
    $container.append(rowHtml);
    self.updateSummary();
    self.initRowBindings($container.find('.multi-entry-row:last'), type, data);

    if (self.config.onEntryAdded) self.config.onEntryAdded(rowNum);
};

MultiEntryManager.prototype.createDiagnosisRow = function(diagnosisList, rowNum) {
    var self = this;
    var optionsHtml = '<option value="">-- Select Diagnosis --</option><option value="custom">✏ Custom / Free-text</option>';
    if (diagnosisList && diagnosisList.length) {
        diagnosisList.forEach(function(d) {
            var icd = d.icd_code || '';
            var label = (icd ? '[' + icd + '] ' : '') + d.diagnosis_name;
            optionsHtml += '<option value="' + d.diagnosis_id + '" data-icd="' + self.escapeHtml(icd) + '" data-cat="' + self.escapeHtml(d.category || '') + '">' + self.escapeHtml(label) + '</option>';
        });
    }

    return `
        <div class="multi-entry-row" data-row="${rowNum}">
            <span class="row-number">${rowNum}</span>
            <button type="button" class="btn btn-danger btn-xs btn-remove-row" title="Remove"><i class="fa fa-times"></i></button>
            <div class="row">
                <div class="col-md-5">
                    <div class="form-group">
                        <label>Diagnosis <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm diagnosis-search" placeholder="Search ICD-10 code or name..." autocomplete="off">
                        <input type="hidden" class="diagnosis-id" name="diagnosis_id[]" value="">
                    </div>
                    <div class="form-group" style="display:none;">
                        <label>Select from List</label>
                        <select class="form-control form-control-sm diagnosis-select">${optionsHtml}</select>
                    </div>
                    <div class="selected-diagnosis" style="margin-top:5px;"></div>
                </div>
                <div class="col-md-3">
                    <div class="form-group custom-diagnosis-field" style="display:none;">
                        <label>Custom Diagnosis</label>
                        <input type="text" class="form-control form-control-sm" name="diagnosis_text[]" placeholder="Enter custom diagnosis">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Remarks</label>
                        <textarea class="form-control form-control-sm" name="remarks[]" rows="2" placeholder="Clinical notes..."></textarea>
                    </div>
                </div>
            </div>
        </div>
    `;
};

MultiEntryManager.prototype.createComplaintRow = function(rowNum) {
    return `
        <div class="multi-entry-row" data-row="${rowNum}">
            <span class="row-number">${rowNum}</span>
            <button type="button" class="btn btn-danger btn-xs btn-remove-row" title="Remove"><i class="fa fa-times"></i></button>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Complaint <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" name="complain[]" placeholder="Enter patient complaint" required>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Duration</label>
                        <input type="text" class="form-control form-control-sm" name="duration[]" placeholder="e.g., 3 days">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Severity</label>
                        <select class="form-control form-control-sm" name="severity[]">
                            <option value="">-- Select --</option>
                            <option value="Mild">Mild</option>
                            <option value="Moderate">Moderate</option>
                            <option value="Severe">Severe</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    `;
};

MultiEntryManager.prototype.createLaboratoryRow = function(labCategories, labTests, rowNum) {
    var self = this;
    var catOptions = '<option value="">All Categories</option>';
    if (labCategories && labCategories.length) {
        labCategories.forEach(function(c) {
            catOptions += '<option value="' + self.escapeHtml(String(c.category_id)) + '">' + self.escapeHtml(c.category_name) + '</option>';
        });
    }
    
    var testOptions = '<option value="">-- Select Test --</option>';
    if (labTests && labTests.length) {
        labTests.forEach(function(t) {
            var nhisTag = t.is_nhis_covered ? ' [NHIS]' : '';
            var priceTag = t.charge_amount > 0 ? ' - GH₵' + parseFloat(t.charge_amount).toFixed(2) : '';
            testOptions += '<option value="' + t.laboratory_id + '" ' +
                'data-category="' + self.escapeHtml(t.category_id || '') + '" ' +
                'data-price="' + (t.charge_amount || 0) + '" ' +
                'data-nhis-price="' + (t.nhis_price || 0) + '" ' +
                'data-nhis="' + (t.is_nhis_covered ? '1' : '0') + '" ' +
                'data-specimen="' + self.escapeHtml(t.specimen_type || '') + '" ' +
                'data-code="' + self.escapeHtml(t.test_code || '') + '">' + 
                self.escapeHtml(t.particular_name) + nhisTag + priceTag + '</option>';
        });
    }

    return `
        <div class="multi-entry-row" data-row="${rowNum}">
            <span class="row-number">${rowNum}</span>
            <button type="button" class="btn btn-danger btn-xs btn-remove-row" title="Remove"><i class="fa fa-times"></i></button>
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Category</label>
                        <select class="form-control form-control-sm lab-category-filter">${catOptions}</select>
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="form-group">
                        <label>Test <span class="text-danger">*</span></label>
                        <div class="input-group" style="margin-bottom:6px;">
                            <span class="input-group-addon"><i class="fa fa-search"></i></span>
                            <input type="text" class="form-control form-control-sm lab-test-search" placeholder="Type 3+ letters to search test..." autocomplete="off">
                            <span class="input-group-btn">
                                <button type="button" class="btn btn-default btn-sm lab-test-search-btn" title="Search"><i class="fa fa-arrow-right"></i></button>
                            </span>
                        </div>
                        <select class="form-control form-control-sm lab-test" name="laboratory_id[]" required>${testOptions}</select>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Priority</label>
                        <select class="form-control form-control-sm" name="priority[]">
                            <option value="Normal">Normal</option>
                            <option value="Urgent">Urgent</option>
                            <option value="STAT">STAT</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Price</label>
                        <input type="text" class="form-control form-control-sm lab-price" readonly placeholder="--">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-8">
                    <div class="form-group">
                        <label>Clinical Notes</label>
                        <input type="text" class="form-control form-control-sm" name="remarks[]" placeholder="Reason for test / clinical indication">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="lab-specimen-note" style="font-size:11px;color:#0097a7;background:#e0f7fa;padding:5px 8px;border-radius:4px;display:none;">
                        <i class="fa fa-flask"></i> Specimen: <span class="specimen-text"></span>
                    </div>
                </div>
            </div>
        </div>
    `;
};

MultiEntryManager.prototype.createSonographyRow = function(sonoItems, rowNum) {
    var self = this;
    var categoryOptions = '<option value="">All Categories</option>';
    var uniqueCategories = {};
    if (sonoItems && sonoItems.length) {
        sonoItems.forEach(function(s) {
            if (s.category && !uniqueCategories[s.category]) {
                uniqueCategories[s.category] = true;
                categoryOptions += '<option value="' + self.escapeHtml(s.category) + '">' + self.escapeHtml(s.category) + '</option>';
            }
        });
    }
    
    var itemOptions = '<option value="">-- Select Scan --</option>';
    if (sonoItems && sonoItems.length) {
        sonoItems.forEach(function(s) {
            var nhisTag = s.is_nhis_covered ? ' [NHIS]' : '';
            var priceTag = s.price > 0 ? ' - GH₵' + parseFloat(s.price).toFixed(2) : '';
            itemOptions += '<option value="' + s.item_id + '" ' +
                'data-category="' + self.escapeHtml(s.category || '') + '" ' +
                'data-price="' + (s.price || 0) + '" ' +
                'data-nhis-price="' + (s.nhis_price || 0) + '" ' +
                'data-nhis="' + (s.is_nhis_covered ? '1' : '0') + '" ' +
                'data-body-part="' + self.escapeHtml(s.body_part || '') + '" ' +
                'data-preparation="' + self.escapeHtml(s.preparation || '') + '">' + 
                self.escapeHtml(s.item_name) + nhisTag + priceTag + '</option>';
        });
    }

    return `
        <div class="multi-entry-row" data-row="${rowNum}">
            <span class="row-number">${rowNum}</span>
            <button type="button" class="btn btn-danger btn-xs btn-remove-row" title="Remove"><i class="fa fa-times"></i></button>
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Category</label>
                        <select class="form-control form-control-sm sono-category-filter">${categoryOptions}</select>
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="form-group">
                        <label>Scan Type <span class="text-danger">*</span></label>
                        <div class="input-group" style="margin-bottom:6px;">
                            <span class="input-group-addon"><i class="fa fa-search"></i></span>
                            <input type="text" class="form-control form-control-sm sono-item-search" placeholder="Type 3+ letters to search scan..." autocomplete="off">
                            <span class="input-group-btn">
                                <button type="button" class="btn btn-default btn-sm sono-item-search-btn" title="Search"><i class="fa fa-arrow-right"></i></button>
                            </span>
                        </div>
                        <select class="form-control form-control-sm sono-item" name="sonography_item_id[]" required>${itemOptions}</select>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Priority</label>
                        <select class="form-control form-control-sm" name="priority[]">
                            <option value="Normal">Normal</option>
                            <option value="Urgent">Urgent</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Price</label>
                        <input type="text" class="form-control form-control-sm sono-rate" readonly placeholder="--">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-8">
                    <div class="form-group">
                        <label>Clinical Indication</label>
                        <input type="text" class="form-control form-control-sm" name="remarks[]" placeholder="Reason for scan (e.g., suspected pregnancy, abdominal pain)">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="sono-preparation-note" style="font-size:11px;color:#856404;background:#fff3cd;padding:5px 8px;border-radius:4px;display:none;">
                        <i class="fa fa-info-circle"></i> <span class="prep-text"></span>
                    </div>
                </div>
            </div>
        </div>
    `;
};

MultiEntryManager.prototype.createRadiologyRow = function(radiologyTests, rowNum) {
    var self = this;
    var testOptions = '<option value="">-- Select Test --</option>';
    if (radiologyTests && radiologyTests.length) {
        radiologyTests.forEach(function(t) {
            var nhisBadge = t.is_nhis_covered ? ' [NHIS]' : '';
            var priceStr = t.price > 0 ? ' (GHS ' + parseFloat(t.price).toFixed(2) + ')' : '';
            testOptions += '<option value="' + t.test_id + '" data-nhis="' + t.is_nhis_covered + '">' + self.escapeHtml(t.test_name) + priceStr + nhisBadge + '</option>';
        });
    }

    return `
        <div class="multi-entry-row" data-row="${rowNum}">
            <span class="row-number">${rowNum}</span>
            <button type="button" class="btn btn-danger btn-xs btn-remove-row" title="Remove"><i class="fa fa-times"></i></button>
            <div class="row">
                <div class="col-md-7">
                    <div class="form-group">
                        <label>Test <span class="text-danger">*</span></label>
                        <div class="input-group" style="margin-bottom:6px;">
                            <span class="input-group-addon"><i class="fa fa-search"></i></span>
                            <input type="text" class="form-control form-control-sm radiology-test-search" placeholder="Type 3+ letters to search test..." autocomplete="off">
                            <span class="input-group-btn">
                                <button type="button" class="btn btn-default btn-sm radiology-test-search-btn" title="Search"><i class="fa fa-arrow-right"></i></button>
                            </span>
                        </div>
                        <select class="form-control form-control-sm radiology-test" name="radiology_test_id[]" required>${testOptions}</select>
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="form-group">
                        <label>Priority</label>
                        <select class="form-control form-control-sm" name="priority[]">
                            <option value="normal">Normal</option>
                            <option value="urgent">Urgent</option>
                            <option value="stat">STAT (Emergency)</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-12">
                    <div class="form-group">
                        <label>Clinical Notes</label>
                        <input type="text" class="form-control form-control-sm" name="clinical_notes[]" placeholder="Reason for test / clinical indication">
                    </div>
                </div>
            </div>
        </div>
    `;
};

MultiEntryManager.prototype.createMedicationRow = function(drugCategories, rowNum) {
    return `
        <div class="multi-entry-row mm-row" data-row="${rowNum}">
            <span class="row-number">${rowNum}</span>
            <button type="button" class="btn btn-danger btn-xs btn-remove-row" title="Remove"><i class="fa fa-times"></i></button>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Drug Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm drug-search" placeholder="Type at least 2 characters to search..." autocomplete="off">
                        <input type="hidden" class="drug-id" name="drug_name[]" value="">
                        <input type="hidden" class="med-nhis-flag" name="is_nhis_covered[]" value="0">
                        <input type="hidden" name="medicine_text[]" class="med-text" value="">
                        <div class="drug-info" style="margin-top:4px;font-size:11px;"></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Dose</label>
                        <div class="mm-dose-group">
                            <input type="text" class="form-control form-control-sm med-dose" name="dosage[]" placeholder="e.g. 500">
                            <select class="form-control form-control-sm med-unit" name="unit[]"><option value="mg">mg</option></select>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Form</label>
                        <select class="form-control form-control-sm med-form" name="drug_form[]"><option value="">-- Form --</option></select>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Frequency <span class="text-danger">*</span></label>
                        <select class="form-control form-control-sm med-freq" name="freq_code[]"><option value="">-- Frequency --</option></select>
                        <input type="hidden" name="frequency[]" class="med-freq-label" value="">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Route</label>
                        <select class="form-control form-control-sm med-route" name="route[]"><option value="">-- Route --</option></select>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Days <span class="text-danger">*</span></label>
                        <input type="number" class="form-control form-control-sm med-days" name="days[]" value="1" min="1">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Qty</label>
                        <input type="number" class="form-control form-control-sm med-qty" name="total_qty[]" value="1" min="1" readonly>
                        <span class="med-qty-auto mm-qty-auto" style="font-size:10px;"></span>
                        <input type="hidden" class="med-qty-override" value="0">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <label class="mm-qty-override-label" style="display:block;margin-top:6px;"><input type="checkbox" class="med-qty-override-chk"> Override qty</label>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-12">
                    <div class="form-group">
                        <label>Instructions</label>
                        <textarea class="form-control form-control-sm med-instruction" name="instruction[]" rows="2" placeholder="Instructions to patient..."></textarea>
                    </div>
                </div>
            </div>
            <div class="med-high-dose-warn mm-high-dose-warn" style="display:none;"></div>
            <div class="mm-preview-label" style="display:none;">Prescription Preview</div>
            <div class="med-preview mm-preview-box" style="display:none;"></div>
        </div>
    `;
};

MultiEntryManager.prototype.removeEntry = function($row) {
    var self = this;
    $row.fadeOut(200, function() {
        $(this).remove();
        self.renumberRows();
        self.updateSummary();
        if (self.config.onEntryRemoved) self.config.onEntryRemoved();
    });
};

MultiEntryManager.prototype.renumberRows = function() {
    var self = this;
    var $container = $('#' + self.config.containerId);
    $container.find('.multi-entry-row').each(function(index) {
        $(this).find('.row-number').text(index + 1);
    });
    if ($container.find('.multi-entry-row').length === 0) {
        var noDataWarning = '';
        if (self.config.module === 'sonography' || self.config.module === 'radiology' || self.config.module === 'laboratory') {
            noDataWarning = '<p style="color:#999;font-size:12px;margin-top:8px;">If items do not appear, check console for data loading errors.</p>';
        }
        $container.html('<div class="empty-state"><i class="fa fa-plus-circle"></i><p>Click "Add" button to add items</p>' + noDataWarning + '</div>');
    }
};

MultiEntryManager.prototype.updateSummary = function() {
    var self = this;
    var count = $('#' + self.config.containerId).find('.multi-entry-row').length;
    $('#' + self.config.countId).text(count);
    $('#' + self.config.saveButtonId).prop('disabled', count === 0);
};

MultiEntryManager.prototype.initRowBindings = function($row, type, data) {
    var self = this;
    $row.find('.btn-remove-row').on('click', function() {
        self.removeEntry($row);
    });

    if (type === 'diagnosis') {
        self.initDiagnosisBindings($row, data);
    } else if (type === 'laboratory') {
        self.initLabBindings($row, data);
    } else if (type === 'sonography') {
        self.initSonographyBindings($row, data);
    } else if (type === 'medication') {
        self.initMedicationBindings($row, data);
    } else if (type === 'radiology') {
        self.initRadiologyBindings($row, data);
    }
};

MultiEntryManager.prototype.initDiagnosisBindings = function($row, data) {
    var self = this;
    var $diagSearch = $row.find('.diagnosis-search');
    var $diagId = $row.find('.diagnosis-id');
    var $diagSelect = $row.find('.diagnosis-select');
    var $selectedDiv = $row.find('.selected-diagnosis');
    var $customField = $row.find('.custom-diagnosis-field');

    // Wire up jQuery UI autocomplete for diagnosis search
    if (typeof SmartMedical !== 'undefined') {
        SmartMedical.initDiagnosisAutocomplete(
            $diagSearch,
            $diagId,
            null, // no separate info selector
            {
                minLength: 2,
                allowCustom: true,
                onSelect: function(item) {
                    if (item.isCustom || item.id === 0) {
                        // Custom/free-text diagnosis
                        $diagId.val('');
                        $diagSelect.val('custom');
                        $customField.show().find('input').val(item.value || item.customName || $diagSearch.val());
                        $selectedDiv.html('<span class="entry-badge entry-badge-info"><i class="fa fa-edit"></i> Custom</span> ' + self.escapeHtml(item.value || item.customName));
                    } else {
                        // Standard diagnosis from master
                        $diagId.val(item.id);
                        $diagSelect.val(item.id);
                        $customField.hide().find('input').val('');
                        var icd = item.data ? item.data.icd_code : (item.icd_code || '');
                        var cat = item.data ? item.data.category : (item.category || '');
                        var badge = icd ? '<span class="entry-badge entry-badge-primary">' + self.escapeHtml(icd) + '</span> ' : '';
                        var catBadge = cat ? '<span class="entry-badge entry-badge-success">' + self.escapeHtml(cat) + '</span> ' : '';
                        $selectedDiv.html(badge + catBadge + '<strong>' + self.escapeHtml(item.value || item.label) + '</strong>');
                    }
                }
            }
        );
    } else {
        console.warn('SmartMedical not loaded — diagnosis autocomplete unavailable');
    }

    // Fallback: dropdown selection
    $diagSelect.on('change', function() {
        var val = $(this).val();
        if (val === 'custom') {
            $diagId.val('');
            $customField.show();
            $selectedDiv.html('<span class="entry-badge entry-badge-info"><i class="fa fa-edit"></i> Custom diagnosis</span>');
        } else if (val) {
            var $opt = $(this).find('option:selected');
            $diagId.val(val);
            $customField.hide().find('input').val('');
            var icd = $opt.data('icd') || '';
            var cat = $opt.data('cat') || '';
            var name = $opt.text().replace(/^\[[A-Z0-9.]+\] /, '');
            var badge = icd ? '<span class="entry-badge entry-badge-primary">' + self.escapeHtml(icd) + '</span> ' : '';
            var catBadge = cat ? '<span class="entry-badge entry-badge-success">' + self.escapeHtml(cat) + '</span> ' : '';
            $selectedDiv.html(badge + catBadge + '<strong>' + self.escapeHtml(name) + '</strong>');
            $diagSearch.val($opt.text());
        } else {
            $diagId.val('');
            $customField.hide();
            $selectedDiv.html('');
        }
    });
};

MultiEntryManager.prototype.initMedicationBindings = function($row, data) {
    var self = this;
    var $drugSearch = $row.find('.drug-search');
    var $drugId = $row.find('.drug-id');
    var $medText = $row.find('.med-text');
    var $drugInfo = $row.find('.drug-info');
    var $nhisFlag = $row.find('.med-nhis-flag');

    // Wire up jQuery UI autocomplete for drug search
    if (typeof SmartMedical !== 'undefined') {
        SmartMedical.initMedicationAutocomplete(
            $drugSearch,
            $drugId,
            $drugInfo,
            {
                minLength: 2,
                allowCustom: true,
                onSelect: function(item) {
                    if (item && item.id && item.id !== 0) {
                        $drugId.val(item.id);
                        $medText.val(item.value || item.label || '');
                        // Set NHIS flag from drug data
                        if (item.data && parseInt(item.data.is_nhis_covered) === 1) {
                            $nhisFlag.val('1');
                            $drugInfo.append(' <span class="label label-success" style="font-size:10px;"><i class="fa fa-check-circle"></i> NHIS</span>');
                        } else {
                            $nhisFlag.val('0');
                        }
                        // Auto-populate form/route/dose from drug master
                        if (item.data) {
                            if (item.data.dosage_form) $row.find('.med-form').val(item.data.dosage_form);
                            if (item.data.route) $row.find('.med-route').val(item.data.route);
                            if (item.data.standard_dosage && !$row.find('.med-dose').val()) {
                                $row.find('.med-dose').val(item.data.standard_dosage);
                            }
                        }
                        $row.trigger('drug-selected', [item.data || item]);
                    } else {
                        // Custom entry
                        $drugId.val('');
                        $medText.val(item.value || item.customName || $drugSearch.val());
                        $nhisFlag.val('0');
                    }
                }
            }
        );
    } else {
        // Fallback: simple keyup search if SmartMedical is not loaded
        console.warn('SmartMedical not loaded — drug autocomplete unavailable');
    }

    // Wire up MedicationModal phase 3 features (frequency, qty calc, preview, high-dose)
    if (typeof MedicationModal !== 'undefined') {
        MedicationModal.bindRow($row, {
            onQtyCalc: function(qty, $r) {
                if (qty !== null) {
                    $r.find('.med-qty').val(qty);
                }
            }
        });
    } else {
        // Fallback: populate frequency dropdown from built-in list
        var $freq = $row.find('.med-freq');
        var freqs = [
            {code:'OD', label:'Once Daily'}, {code:'BD', label:'Twice Daily'},
            {code:'TDS', label:'Three Times Daily'}, {code:'QID', label:'Four Times Daily'},
            {code:'STAT', label:'Immediately'}, {code:'PRN', label:'As Needed'},
            {code:'ON', label:'At Night'}, {code:'Q4H', label:'Every 4 Hours'},
            {code:'Q6H', label:'Every 6 Hours'}, {code:'Q8H', label:'Every 8 Hours'},
            {code:'Q12H', label:'Every 12 Hours'}
        ];
        freqs.forEach(function(f) {
            $freq.append('<option value="' + f.code + '">' + f.code + ' \u2014 ' + f.label + '</option>');
        });

        // Simple qty calculation fallback
        var dpdMap = {OD:1,BD:2,TDS:3,QID:4,Q4H:6,Q6H:4,Q8H:3,Q12H:2,STAT:1,PRN:0,ON:1};
        function calcQty() {
            var freqCode = $row.find('.med-freq').val();
            var days = parseInt($row.find('.med-days').val()) || 0;
            var dpd = dpdMap[freqCode] || 0;
            if (dpd > 0 && days > 0) {
                $row.find('.med-qty').val(Math.ceil(dpd * days));
            }
        }
        $row.find('.med-freq').on('change', function() {
            var code = $(this).val();
            var match = freqs.filter(function(f) { return f.code === code; });
            $row.find('.med-freq-label').val(match.length ? code + ' (' + match[0].label + ')' : code);
            calcQty();
        });
        $row.find('.med-days').on('input change', calcQty);

        // Populate route/form/unit fallbacks
        var routes = ['Oral','IV','IM','Topical','Subcutaneous','Inhalation','Sublingual','Rectal'];
        var $route = $row.find('.med-route');
        routes.forEach(function(r) { $route.append('<option value="' + r + '">' + r + '</option>'); });

        var forms = ['Tablet','Capsule','Syrup','Injection','Suspension','Cream','Ointment','Drops','Inhaler'];
        var $form = $row.find('.med-form');
        forms.forEach(function(f) { $form.append('<option value="' + f + '">' + f + '</option>'); });

        var units = ['mg','g','mcg','ml','tablet','capsule','drop','puff','IU'];
        var $unit = $row.find('.med-unit');
        $unit.empty();
        units.forEach(function(u) { $unit.append('<option value="' + u + '"' + (u === 'mg' ? ' selected' : '') + '>' + u + '</option>'); });
    }

    // Qty override checkbox
    $row.find('.med-qty-override-chk').on('change', function() {
        var overridden = $(this).is(':checked');
        $row.find('.med-qty').prop('readonly', !overridden);
        $row.find('.med-qty-override').val(overridden ? '1' : '0');
    });
};

MultiEntryManager.prototype.initLabBindings = function($row, data) {
    var self = this;
    var $categoryFilter = $row.find('.lab-category-filter');
    var $test = $row.find('.lab-test');
    var $price = $row.find('.lab-price');
    var $specimenNote = $row.find('.lab-specimen-note');
    var $specimenText = $row.find('.specimen-text');
    var $search = $row.find('.lab-test-search');
    var $searchBtn = $row.find('.lab-test-search-btn');

    // Type-to-search: fast lab test lookup (3+ chars)
    if ($search.length && typeof $.fn.autocomplete === 'function') {
        var tests = (data && data.tests && Array.isArray(data.tests)) ? data.tests : [];

        $search.autocomplete({
            minLength: 3,
            delay: 120,
            source: function(request, response) {
                var term = (request.term || '').toLowerCase();
                if (term.length < 3) return response([]);
                var out = [];
                for (var i = 0; i < tests.length; i++) {
                    var t = tests[i] || {};
                    var name = (t.particular_name || '').toString();
                    if (!name) continue;
                    if (name.toLowerCase().indexOf(term) !== -1) {
                        out.push({
                            label: name,
                            value: name,
                            id: t.laboratory_id,
                            category_id: t.category_id
                        });
                        if (out.length >= 15) break;
                    }
                }
                response(out);
            },
            select: function(event, ui) {
                if (ui && ui.item && ui.item.id) {
                    // Ensure the selected option is visible regardless of category filter
                    $categoryFilter.val('');
                    $categoryFilter.trigger('change');

                    $test.val(String(ui.item.id));
                    $test.trigger('change');
                    $search.val(ui.item.label);
                }
                return false;
            }
        });

        $search.on('blur', function() {
            // Avoid leaving stale text that doesn't match a selection
            var selVal = $test.val();
            if (!selVal) return;
            var selText = $test.find('option:selected').text() || '';
            if (selText && !$search.val()) {
                $search.val(selText.replace(/\s*-\s*GH.*$/, '').replace(/\s*\[NHIS\].*$/, '').trim());
            }
        });

        $searchBtn.on('click', function() {
            $search.focus();
            if (($search.val() || '').length >= 3) {
                $search.autocomplete('search');
            }
        });
    }

    $categoryFilter.on('change', function() {
        var selectedCat = $(this).val();
        $test.find('option').each(function() {
            var $opt = $(this);
            if (!$opt.val()) return;
            var optCat = $opt.data('category') || '';
            if (!selectedCat || String(optCat) === String(selectedCat)) {
                $opt.show();
            } else {
                $opt.hide();
            }
        });
        var $selected = $test.find('option:selected');
        if ($selected.val() && $selected.css('display') === 'none') {
            $test.val('');
            $price.val('');
            $specimenNote.hide();
        }
    });

    $test.on('change', function() {
        var $selected = $(this).find('option:selected');
        var price = $selected.data('price') || 0;
        var specimen = $selected.data('specimen') || '';
        
        if (price > 0) {
            $price.val('GH₵' + parseFloat(price).toFixed(2));
        } else {
            $price.val('--');
        }
        
        if (specimen) {
            $specimenText.text(specimen);
            $specimenNote.show();
        } else {
            $specimenNote.hide();
        }
    });
};

MultiEntryManager.prototype.initSonographyBindings = function($row, data) {
    var $categoryFilter = $row.find('.sono-category-filter');
    var $item = $row.find('.sono-item');
    var $rate = $row.find('.sono-rate');
    var $prepNote = $row.find('.sono-preparation-note');
    var $prepText = $row.find('.prep-text');
    var $search = $row.find('.sono-item-search');
    var $searchBtn = $row.find('.sono-item-search-btn');

    if ($search.length && typeof $.fn.autocomplete === 'function') {
        var items = (data && data.items && Array.isArray(data.items)) ? data.items : [];

        $search.autocomplete({
            minLength: 3,
            delay: 120,
            source: function(request, response) {
                var term = (request.term || '').toLowerCase();
                if (term.length < 3) return response([]);
                var out = [];
                for (var i = 0; i < items.length; i++) {
                    var s = items[i] || {};
                    var name = (s.item_name || '').toString();
                    if (!name) continue;
                    if (name.toLowerCase().indexOf(term) !== -1) {
                        out.push({
                            label: name,
                            value: name,
                            id: s.item_id,
                            category: s.category
                        });
                        if (out.length >= 15) break;
                    }
                }
                response(out);
            },
            select: function(event, ui) {
                if (ui && ui.item && ui.item.id) {
                    $categoryFilter.val('');
                    $categoryFilter.trigger('change');

                    $item.val(String(ui.item.id));
                    $item.trigger('change');
                    $search.val(ui.item.label);
                }
                return false;
            }
        });

        $search.on('blur', function() {
            var selVal = $item.val();
            if (!selVal) return;
            var selText = $item.find('option:selected').text() || '';
            if (selText && !$search.val()) {
                $search.val(selText.replace(/\s*-\s*GH.*$/, '').replace(/\s*\[NHIS\].*$/, '').trim());
            }
        });

        $searchBtn.on('click', function() {
            $search.focus();
            if (($search.val() || '').length >= 3) {
                $search.autocomplete('search');
            }
        });
    }

    $categoryFilter.on('change', function() {
        var selectedCat = $(this).val();
        $item.find('option').each(function() {
            var $opt = $(this);
            if (!$opt.val()) return;
            var optCat = $opt.data('category') || '';
            if (!selectedCat || optCat === selectedCat) {
                $opt.show();
            } else {
                $opt.hide();
            }
        });
        var $selected = $item.find('option:selected');
        if ($selected.val() && $selected.css('display') === 'none') {
            $item.val('');
            $rate.val('');
            $prepNote.hide();
        }
    });

    $item.on('change', function() {
        var $selected = $(this).find('option:selected');
        var price = $selected.data('price') || 0;
        var preparation = $selected.data('preparation') || '';
        
        if (price > 0) {
            $rate.val('GH₵' + parseFloat(price).toFixed(2));
        } else {
            $rate.val('--');
        }
        
        if (preparation) {
            $prepText.text(preparation);
            $prepNote.show();
        } else {
            $prepNote.hide();
        }
    });
};

MultiEntryManager.prototype.initRadiologyBindings = function($row, data) {
    var $test = $row.find('.radiology-test');
    var $search = $row.find('.radiology-test-search');
    var $searchBtn = $row.find('.radiology-test-search-btn');

    if ($search.length && typeof $.fn.autocomplete === 'function') {
        var tests = (data && data.tests && Array.isArray(data.tests)) ? data.tests : [];

        $search.autocomplete({
            minLength: 3,
            delay: 120,
            source: function(request, response) {
                var term = (request.term || '').toLowerCase();
                if (term.length < 3) return response([]);
                var out = [];
                for (var i = 0; i < tests.length; i++) {
                    var t = tests[i] || {};
                    var name = (t.test_name || '').toString();
                    if (!name) continue;
                    if (name.toLowerCase().indexOf(term) !== -1) {
                        out.push({
                            label: name,
                            value: name,
                            id: t.test_id
                        });
                        if (out.length >= 15) break;
                    }
                }
                response(out);
            },
            select: function(event, ui) {
                if (ui && ui.item && ui.item.id) {
                    $test.val(String(ui.item.id));
                    $test.trigger('change');
                    $search.val(ui.item.label);
                }
                return false;
            }
        });

        $search.on('blur', function() {
            var selVal = $test.val();
            if (!selVal) return;
            var selText = $test.find('option:selected').text() || '';
            if (selText && !$search.val()) {
                $search.val(selText.replace(/\s*\(GHS.*$/, '').replace(/\s*\[NHIS\].*$/, '').trim());
            }
        });

        $searchBtn.on('click', function() {
            $search.focus();
            if (($search.val() || '').length >= 3) {
                $search.autocomplete('search');
            }
        });
    }
};

MultiEntryManager.prototype.collectEntries = function(type) {
    var self = this;
    var entries = [];
    $('#' + self.config.containerId).find('.multi-entry-row').each(function() {
        var $row = $(this);
        var entry = {};

        switch(type) {
            case 'diagnosis':
                entry.diagnosis_id = $row.find('[name="diagnosis_id[]"]').val() || '';
                entry.diagnosis_text = $row.find('[name="diagnosis_text[]"]').val() || '';
                entry.remarks = $row.find('[name="remarks[]"]').val() || '';
                // Must have either diagnosis_id or diagnosis_text
                if (entry.diagnosis_id || entry.diagnosis_text) entries.push(entry);
                break;
            case 'laboratory':
                entry.laboratory_id = $row.find('[name="laboratory_id[]"]').val();
                entry.priority = $row.find('[name="priority[]"]').val() || 'Normal';
                entry.remarks = $row.find('[name="remarks[]"]').val() || '';
                if (entry.laboratory_id) entries.push(entry);
                break;
            case 'sonography':
                entry.sonography_item_id = $row.find('[name="sonography_item_id[]"]').val();
                entry.priority = $row.find('[name="priority[]"]').val() || 'Normal';
                entry.remarks = $row.find('[name="remarks[]"]').val() || '';
                if (entry.sonography_item_id) entries.push(entry);
                break;
            case 'medication':
                entry.drug_name = $row.find('[name="drug_name[]"]').val() || '';
                entry.medicine_text = $row.find('[name="medicine_text[]"]').val() || $row.find('.drug-search').val() || '';
                entry.dosage = $row.find('[name="dosage[]"]').val() || '';
                entry.frequency = $row.find('[name="frequency[]"]').val() || '';
                entry.freq_code = $row.find('[name="freq_code[]"]').val() || '';
                entry.days = $row.find('[name="days[]"]').val() || '1';
                entry.total_qty = $row.find('[name="total_qty[]"]').val() || '1';
                entry.instruction = $row.find('[name="instruction[]"]').val() || '';
                entry.route = $row.find('[name="route[]"]').val() || '';
                entry.drug_form = $row.find('[name="drug_form[]"]').val() || '';
                entry.unit = $row.find('[name="unit[]"]').val() || '';
                entry.is_nhis_covered = $row.find('[name="is_nhis_covered[]"]').val() || '0';
                if (entry.drug_name || entry.medicine_text) entries.push(entry);
                break;
            case 'radiology':
                entry.radiology_test_id = $row.find('[name="radiology_test_id[]"]').val();
                entry.radiology_test_text = $row.find('[name="radiology_test_id[]"] option:selected').text().replace(/\s*\(GHS.*$/, '').replace(/\s*\[NHIS\].*$/, '').trim();
                entry.priority = $row.find('[name="priority[]"]').val() || 'normal';
                entry.clinical_notes = $row.find('[name="clinical_notes[]"]').val() || '';
                if (entry.radiology_test_id) entries.push(entry);
                break;
        }
    });
    return entries;
};

MultiEntryManager.prototype.saveAll = function(type, opdNo, patientNo, callback) {
    var self = this;
    var entries = self.collectEntries(type);
    if (entries.length === 0) {
        alert('Please add at least one item before saving.');
        return;
    }

    // Find the visible/open modal that contains our container
    var $container = $('#' + self.config.containerId);
    var $modal = $container.closest('.modal');
    $modal.find('.modal-body').append('<div class="saving-overlay"><i class="fa fa-spinner fa-spin"></i></div>');

    // Get CSRF token from the hidden input inside this modal first, then fallback to any on page
    var csrfName = 'hms_csrf_token';
    var csrfToken = '';
    var $csrfInput = $modal.find('input[name="hms_csrf_token"]');
    if ($csrfInput.length) {
        csrfToken = $csrfInput.val();
    }
    if (!csrfToken) {
        // Fallback: get from any csrf input on the page
        $csrfInput = $('input[name="hms_csrf_token"]');
        if ($csrfInput.length) {
            csrfToken = $csrfInput.first().val();
        }
    }
    if (!csrfToken) {
        // Last resort: try cookie
        var cookies = document.cookie.split(';');
        for (var i = 0; i < cookies.length; i++) {
            var cookie = cookies[i].trim();
            if (cookie.indexOf('hms_csrf_cookie=') === 0) {
                csrfToken = cookie.substring('hms_csrf_cookie='.length);
                break;
            }
        }
    }

    console.log('DEBUG saveAll: type=' + type + ', entries=' + entries.length + ', csrf=' + (csrfToken ? 'found' : 'MISSING'));

    var postData = {
        opd_no: opdNo,
        patient_no: patientNo,
        entries: JSON.stringify(entries)
    };
    postData[csrfName] = csrfToken;

    $.ajax({
        url: self.baseUrl + (self.config.saveUrlPrefix || 'app/opd/') + 'save_' + type + '_batch',
        type: 'POST',
        data: postData,
        dataType: 'json',
        success: function(response) {
            $modal.find('.saving-overlay').remove();
            // Update CSRF token if server sent a new one
            if (response.csrf_token) {
                $('input[name="hms_csrf_token"]').val(response.csrf_token);
            }
            if (response.success) {
                if (self.config.onSaveSuccess) self.config.onSaveSuccess(response);
                if (callback) callback(true, response);
            } else {
                alert(response.message || 'Error saving items');
                if (self.config.onSaveError) self.config.onSaveError(response);
                if (callback) callback(false, response);
            }
        },
        error: function(xhr, status, error) {
            $modal.find('.saving-overlay').remove();
            console.error('Save error - Status:', xhr.status, 'Response:', xhr.responseText);
            var errorMsg = 'Network error. Please try again.';
            if (xhr.status === 403) {
                errorMsg = 'Session expired or security token invalid. Please refresh the page and try again.';
            } else if (xhr.responseText) {
                try {
                    var resp = JSON.parse(xhr.responseText);
                    if (resp.message) errorMsg = resp.message;
                } catch(e) {
                    if (xhr.responseText.indexOf('error') > -1 || xhr.responseText.indexOf('Error') > -1) {
                        errorMsg = 'Server error occurred. Check console for details.';
                    }
                }
            }
            alert(errorMsg);
            if (callback) callback(false, { message: error });
        }
    });
};

MultiEntryManager.prototype.resetModal = function() {
    var self = this;
    $('#' + self.config.containerId).html('<div class="empty-state"><i class="fa fa-plus-circle"></i><p>Click "Add" button to add items</p></div>');
    self.rowCounter = 0;
    self.updateSummary();
};
