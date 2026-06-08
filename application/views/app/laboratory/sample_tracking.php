<?php $this->load->view('includes/header'); ?>

<div class="content-wrapper">
    <section class="content-header">
        <h1>
            <i class="fa fa-barcode text-info"></i> Sample Tracking
        </h1>
        <ol class="breadcrumb">
            <li><a href="<?php echo base_url(); ?>app/laboratory"><i class="fa fa-flask"></i> Laboratory</a></li>
            <li class="active">Sample Tracking</li>
        </ol>
    </section>

    <section class="content">
        <?php if (isset($message) && $message): ?>
            <?php echo $message; ?>
        <?php endif; ?>

        <div class="row">
            <!-- Barcode Scanner -->
            <div class="col-md-4">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-qrcode"></i> Scan Sample</h3>
                    </div>
                    <div class="box-body">
                        <div class="form-group">
                            <label>Sample Barcode:</label>
                            <div class="input-group">
                                <input type="text" id="barcodeInput" class="form-control" placeholder="Scan or enter barcode..." autofocus>
                                <span class="input-group-btn">
                                    <button type="button" id="scanBtn" class="btn btn-primary">
                                        <i class="fa fa-search"></i> Find
                                    </button>
                                </span>
                            </div>
                        </div>
                        <div id="scanResult" style="display:none;">
                            <!-- Results will be loaded here -->
                        </div>
                    </div>
                </div>

                <!-- Status Update -->
                <div class="box box-info" id="statusUpdateBox" style="display:none;">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-edit"></i> Update Status</h3>
                    </div>
                    <div class="box-body">
                        <input type="hidden" id="currentSampleId">
                        <div class="form-group">
                            <label>New Status:</label>
                            <select id="newStatus" class="form-control">
                                <option value="">-- Select Status --</option>
                                <option value="COLLECTED">COLLECTED</option>
                                <option value="RECEIVED_LAB">RECEIVED IN LAB</option>
                                <option value="IN_PROCESS">IN PROCESS</option>
                                <option value="RESULT_READY">RESULT READY</option>
                                <option value="VERIFIED">VERIFIED</option>
                                <option value="REJECTED">REJECTED</option>
                                <option value="DISPOSED">DISPOSED</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Location:</label>
                            <input type="text" id="newLocation" class="form-control" placeholder="e.g., Rack A-12">
                        </div>
                        <div class="form-group">
                            <label>Notes:</label>
                            <textarea id="statusNotes" class="form-control" rows="2"></textarea>
                        </div>
                        <button type="button" id="updateStatusBtn" class="btn btn-success btn-block">
                            <i class="fa fa-save"></i> Update Status
                        </button>
                    </div>
                </div>
            </div>

            <!-- Sample Details -->
            <div class="col-md-8">
                <div class="box box-default" id="sampleDetailsBox" style="display:none;">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-info-circle"></i> Sample Details</h3>
                    </div>
                    <div class="box-body" id="sampleDetails">
                        <!-- Sample details will be loaded here -->
                    </div>
                </div>

                <!-- Sample Lifecycle -->
                <div class="box box-default">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-sitemap"></i> Sample Lifecycle</h3>
                    </div>
                    <div class="box-body">
                        <div class="timeline">
                            <div class="time-label"><span class="bg-blue">Sample Workflow</span></div>
                            
                            <div><i class="fa fa-file-text bg-gray"></i>
                                <div class="timeline-item">
                                    <h3 class="timeline-header">REQUESTED</h3>
                                    <div class="timeline-body">Test ordered by physician</div>
                                </div>
                            </div>
                            
                            <div><i class="fa fa-syringe bg-aqua"></i>
                                <div class="timeline-item">
                                    <h3 class="timeline-header">COLLECTED</h3>
                                    <div class="timeline-body">Sample collected from patient (phlebotomy)</div>
                                </div>
                            </div>
                            
                            <div><i class="fa fa-building bg-blue"></i>
                                <div class="timeline-item">
                                    <h3 class="timeline-header">RECEIVED IN LAB</h3>
                                    <div class="timeline-body">Sample received and logged in laboratory</div>
                                </div>
                            </div>
                            
                            <div><i class="fa fa-cogs bg-yellow"></i>
                                <div class="timeline-item">
                                    <h3 class="timeline-header">IN PROCESS</h3>
                                    <div class="timeline-body">Sample being analyzed</div>
                                </div>
                            </div>
                            
                            <div><i class="fa fa-file-medical bg-green"></i>
                                <div class="timeline-item">
                                    <h3 class="timeline-header">RESULT READY</h3>
                                    <div class="timeline-body">Analysis complete, pending verification</div>
                                </div>
                            </div>
                            
                            <div><i class="fa fa-check-double bg-green"></i>
                                <div class="timeline-item">
                                    <h3 class="timeline-header">VERIFIED</h3>
                                    <div class="timeline-body">Result verified and released</div>
                                </div>
                            </div>
                            
                            <div><i class="fa fa-clock bg-gray"></i></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<script>
var csrfName = '<?php echo $this->security->get_csrf_token_name(); ?>';
var csrfHash = '<?php echo $this->security->get_csrf_hash(); ?>';
$(document).ready(function() {
    // Scan on Enter key
    $('#barcodeInput').on('keypress', function(e) {
        if (e.which === 13) {
            scanSample();
        }
    });

    $('#scanBtn').on('click', scanSample);
    $('#updateStatusBtn').on('click', updateStatus);

    function scanSample() {
        var barcode = $('#barcodeInput').val().trim();
        if (!barcode) {
            alert('Please enter a barcode');
            return;
        }

        var scanData = { barcode: barcode };
        scanData[csrfName] = csrfHash;
        $.ajax({
            url: '<?php echo base_url(); ?>app/laboratory/scan_sample',
            type: 'POST',
            data: scanData,
            dataType: 'json',
            success: function(response) {
                if (response.ok) {
                    displaySample(response.sample);
                } else {
                    $('#scanResult').html('<div class="alert alert-danger"><i class="fa fa-times"></i> ' + response.error + '</div>').show();
                    $('#sampleDetailsBox, #statusUpdateBox').hide();
                }
            },
            error: function() {
                $('#scanResult').html('<div class="alert alert-danger"><i class="fa fa-times"></i> Error scanning barcode</div>').show();
            }
        });
    }

    function displaySample(sample) {
        var statusClass = {
            'REQUESTED': 'default',
            'COLLECTED': 'info',
            'RECEIVED_LAB': 'primary',
            'IN_PROCESS': 'warning',
            'RESULT_READY': 'success',
            'VERIFIED': 'success',
            'REJECTED': 'danger',
            'DISPOSED': 'default'
        };

        var html = '<div class="table-responsive"><table class="table table-bordered">';
        html += '<tr><th width="30%">Barcode</th><td><strong class="text-primary">' + sample.sample_barcode + '</strong></td></tr>';
        html += '<tr><th>Patient</th><td>' + sample.patient_no + '</td></tr>';
        html += '<tr><th>Test</th><td>' + (sample.test_name || 'N/A') + '</td></tr>';
        html += '<tr><th>Sample Type</th><td>' + (sample.sample_type || 'N/A') + '</td></tr>';
        html += '<tr><th>Status</th><td><span class="label label-' + (statusClass[sample.sample_status] || 'default') + '">' + sample.sample_status + '</span></td></tr>';
        html += '<tr><th>Location</th><td>' + (sample.sample_location || 'N/A') + '</td></tr>';
        if (sample.collected_at) {
            html += '<tr><th>Collected</th><td>' + sample.collected_at + '</td></tr>';
        }
        if (sample.received_at) {
            html += '<tr><th>Received in Lab</th><td>' + sample.received_at + '</td></tr>';
        }
        html += '</table></div>';

        $('#sampleDetails').html(html);
        $('#sampleDetailsBox').show();
        $('#currentSampleId').val(sample.sample_id);
        $('#statusUpdateBox').show();
        $('#scanResult').html('<div class="alert alert-success"><i class="fa fa-check"></i> Sample found</div>').show();
    }

    function updateStatus() {
        var sampleId = $('#currentSampleId').val();
        var status = $('#newStatus').val();
        var location = $('#newLocation').val();
        var notes = $('#statusNotes').val();

        if (!status) {
            alert('Please select a status');
            return;
        }

        var updateData = {
                sample_id: sampleId,
                status: status,
                location: location,
                notes: notes
            };
        updateData[csrfName] = csrfHash;
        $.ajax({
            url: '<?php echo base_url(); ?>app/laboratory/update_sample_status',
            type: 'POST',
            data: updateData,
            dataType: 'json',
            success: function(response) {
                if (response.ok) {
                    alert('Status updated successfully');
                    // Re-scan to refresh
                    scanSample();
                    // Clear form
                    $('#newStatus').val('');
                    $('#newLocation').val('');
                    $('#statusNotes').val('');
                } else {
                    alert('Error: ' + (response.error || 'Unknown error'));
                }
            },
            error: function() {
                alert('Error updating status');
            }
        });
    }
});
</script>

<?php $this->load->view('includes/footer'); ?>
