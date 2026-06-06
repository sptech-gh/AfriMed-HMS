<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Insurance Companies - Hebrew Medical Center</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <link href="<?php echo base_url() ?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/css/datatables/dataTables.bootstrap.css" rel="stylesheet" type="text/css" />
</head>
<body class="skin-blue">
    <?php require_once(APPPATH . 'views/include/header.php'); ?>

    <div class="wrapper row-offcanvas row-offcanvas-left">
        <?php require_once(APPPATH . 'views/include/sidebar.php'); ?>

        <aside class="right-side">
            <section class="content-header">
                <h1><i class="fa fa-shield"></i> Insurance Companies <small>Private Insurance Management</small></h1>
                <ol class="breadcrumb">
                    <li><a href="<?php echo base_url() ?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                    <li><a href="<?php echo base_url() ?>app/service_queue">Service Queue</a></li>
                    <li class="active">Insurance</li>
                </ol>
            </section>

            <section class="content">
                <?php if ($this->session->flashdata('message')) { echo $this->session->flashdata('message'); } ?>

                <div class="row">
                    <div class="col-md-12">
                        <div class="box box-primary">
                            <div class="box-header with-border">
                                <h3 class="box-title">Insurance Companies</h3>
                                <div class="box-tools pull-right">
                                    <button class="btn btn-success btn-sm" data-toggle="modal" data-target="#addInsuranceModal">
                                        <i class="fa fa-plus"></i> Add Insurance Company
                                    </button>
                                </div>
                            </div>
                            <div class="box-body table-responsive">
                                <table id="tblInsurance" class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>Code</th>
                                            <th>Insurance Name</th>
                                            <th>Coverage Type</th>
                                            <th>Default %</th>
                                            <th>Contact Person</th>
                                            <th>Phone</th>
                                            <th>Email</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (isset($insurance_companies) && count($insurance_companies) > 0) { ?>
                                            <?php foreach ($insurance_companies as $ins) { ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($ins->insurance_code); ?></td>
                                                <td><strong><?php echo htmlspecialchars($ins->insurance_name); ?></strong></td>
                                                <td><span class="label label-info"><?php echo $ins->coverage_type; ?></span></td>
                                                <td class="text-center"><?php echo number_format($ins->default_percent, 0); ?>%</td>
                                                <td><?php echo htmlspecialchars($ins->contact_person); ?></td>
                                                <td><?php echo htmlspecialchars($ins->phone); ?></td>
                                                <td><?php echo htmlspecialchars($ins->email); ?></td>
                                                <td>
                                                    <button class="btn btn-xs btn-info btn-coverage" data-id="<?php echo $ins->insurance_id; ?>" data-name="<?php echo htmlspecialchars($ins->insurance_name); ?>" data-percent="<?php echo $ins->default_percent; ?>">
                                                        <i class="fa fa-percent"></i> Coverage Rules
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php } ?>
                                        <?php } else { ?>
                                            <tr><td colspan="8" class="text-center text-muted">No insurance companies registered</td></tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Info Box -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="box box-info collapsed-box">
                            <div class="box-header with-border">
                                <h3 class="box-title"><i class="fa fa-info-circle"></i> Insurance Coverage Guide</h3>
                                <div class="box-tools pull-right">
                                    <button class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-plus"></i></button>
                                </div>
                            </div>
                            <div class="box-body">
                                <h4>Coverage Types</h4>
                                <ul>
                                    <li><strong>PERCENTAGE:</strong> Insurance covers a percentage of the bill (e.g., 80%)</li>
                                    <li><strong>FIXED:</strong> Insurance covers a fixed amount per service</li>
                                    <li><strong>COPAY:</strong> Patient pays a fixed copay, insurance covers the rest</li>
                                </ul>
                                <h4>Billing Priority</h4>
                                <p>When calculating prices, the system checks in this order:</p>
                                <ol>
                                    <li>Company/Corporate pricing (if patient linked to company)</li>
                                    <li>Private Insurance coverage (this section)</li>
                                    <li>NHIS coverage (if patient has valid NHIS)</li>
                                    <li>Default cash price</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </aside>
    </div>

    <!-- Add Insurance Modal -->
    <div class="modal fade" id="addInsuranceModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-green">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title"><i class="fa fa-shield"></i> Add Insurance Company</h4>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Insurance Name *</label>
                        <input type="text" id="insurance_name" class="form-control" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Insurance Code</label>
                                <input type="text" id="insurance_code" class="form-control" placeholder="e.g., STAR, METRO">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Coverage Type</label>
                                <select id="coverage_type" class="form-control">
                                    <option value="PERCENTAGE">Percentage</option>
                                    <option value="FIXED">Fixed Amount</option>
                                    <option value="COPAY">Copay</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Default Coverage Percentage</label>
                        <div class="input-group">
                            <input type="number" id="default_percent" class="form-control" value="80" min="0" max="100">
                            <span class="input-group-addon">%</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Contact Person</label>
                        <input type="text" id="contact_person" class="form-control">
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Phone</label>
                                <input type="text" id="phone" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" id="email" class="form-control">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Address</label>
                        <textarea id="address" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="btnAddInsurance"><i class="fa fa-save"></i> Save Insurance</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Coverage Rules Modal -->
    <div class="modal fade" id="coverageModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-blue">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title"><i class="fa fa-percent"></i> Set Coverage Rules</h4>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="coverage_insurance_id">
                    <p><strong>Insurance:</strong> <span id="coverage_insurance_name"></span></p>
                    <div class="form-group">
                        <label>Service Type (leave blank for all)</label>
                        <select id="coverage_service_type" class="form-control">
                            <option value="">All Services</option>
                            <option value="LAB">Laboratory Tests</option>
                            <option value="SONOGRAPHY">Sonography/Radiology</option>
                            <option value="PROCEDURE">Procedures</option>
                            <option value="MEDICATION">Medications</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Coverage Percentage</label>
                        <div class="input-group">
                            <input type="number" id="coverage_percent" class="form-control" value="80" min="0" max="100">
                            <span class="input-group-addon">%</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Maximum Amount (optional)</label>
                        <div class="input-group">
                            <span class="input-group-addon">GHS</span>
                            <input type="number" id="coverage_max_amount" class="form-control" placeholder="Leave blank for no limit" step="0.01">
                        </div>
                        <small class="text-muted">Maximum amount insurance will cover per service</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="btnSaveCoverage"><i class="fa fa-save"></i> Save Coverage Rule</button>
                </div>
            </div>
        </div>
    </div>

    <script src="<?php echo base_url(); ?>public/js/jquery.min.js"></script>
    <script src="<?php echo base_url(); ?>public/js/bootstrap.min.js"></script>
    <script src="<?php echo base_url(); ?>public/js/AdminLTE/app.js"></script>
    <script src="<?php echo base_url(); ?>public/js/datatables/jquery.dataTables.js"></script>
    <script src="<?php echo base_url(); ?>public/js/datatables/dataTables.bootstrap.js"></script>
    <script>
    var csrfName = '<?php echo $this->security->get_csrf_token_name(); ?>';
    var csrfHash = '<?php echo $this->security->get_csrf_hash(); ?>';
    $(function() {
        $('#tblInsurance').DataTable({"pageLength": 25});

        // Add insurance
        $('#btnAddInsurance').click(function() {
            var name = $('#insurance_name').val().trim();
            if (!name) {
                alert('Insurance name is required');
                return;
            }
            
            var btn = $(this);
            btn.prop('disabled', true);
            
            var insuranceData = {
                insurance_name: name,
                insurance_code: $('#insurance_code').val(),
                coverage_type: $('#coverage_type').val(),
                default_percent: $('#default_percent').val(),
                contact_person: $('#contact_person').val(),
                phone: $('#phone').val(),
                email: $('#email').val(),
                address: $('#address').val()
            };
            insuranceData[csrfName] = csrfHash;
            $.post('<?php echo base_url(); ?>app/service_queue/add_insurance', insuranceData, function(resp) {
                if (resp.success) {
                    alert('Insurance company added successfully');
                    location.reload();
                } else {
                    alert('Error: ' + resp.error);
                    btn.prop('disabled', false);
                }
            }, 'json').fail(function() {
                alert('Request failed');
                btn.prop('disabled', false);
            });
        });

        // Open coverage modal
        $('.btn-coverage').click(function() {
            $('#coverage_insurance_id').val($(this).data('id'));
            $('#coverage_insurance_name').text($(this).data('name'));
            $('#coverage_percent').val($(this).data('percent'));
            $('#coverageModal').modal('show');
        });

        // Save coverage
        $('#btnSaveCoverage').click(function() {
            var btn = $(this);
            btn.prop('disabled', true);
            
            var coverageData = {
                insurance_id: $('#coverage_insurance_id').val(),
                service_type: $('#coverage_service_type').val(),
                coverage_percent: $('#coverage_percent').val(),
                max_amount: $('#coverage_max_amount').val()
            };
            coverageData[csrfName] = csrfHash;
            $.post('<?php echo base_url(); ?>app/service_queue/set_insurance_coverage', coverageData, function(resp) {
                if (resp.success) {
                    alert('Coverage rule saved');
                    $('#coverageModal').modal('hide');
                } else {
                    alert('Error: ' + (resp.error || 'Failed to save'));
                }
                btn.prop('disabled', false);
            }, 'json').fail(function() {
                alert('Request failed');
                btn.prop('disabled', false);
            });
        });
    });
    </script>
</body>
</html>
