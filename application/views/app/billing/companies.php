<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Corporate Companies - Hebrew Medical Center</title>
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
                <h1><i class="fa fa-building"></i> Corporate Companies <small>Company/Corporate Billing Management</small></h1>
                <ol class="breadcrumb">
                    <li><a href="<?php echo base_url() ?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                    <li><a href="<?php echo base_url() ?>app/service_queue">Service Queue</a></li>
                    <li class="active">Companies</li>
                </ol>
            </section>

            <section class="content">
                <?php if ($this->session->flashdata('message')) { echo $this->session->flashdata('message'); } ?>

                <div class="row">
                    <div class="col-md-12">
                        <div class="box box-primary">
                            <div class="box-header with-border">
                                <h3 class="box-title">Corporate Companies</h3>
                                <div class="box-tools pull-right">
                                    <a href="<?php echo base_url(); ?>app/service_queue/insurance" class="btn btn-info btn-sm">
                                        <i class="fa fa-shield"></i> Insurance Companies
                                    </a>
                                    <button class="btn btn-success btn-sm" data-toggle="modal" data-target="#addCompanyModal">
                                        <i class="fa fa-plus"></i> Add Company
                                    </button>
                                </div>
                            </div>
                            <div class="box-body table-responsive">
                                <table id="tblCompanies" class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>Code</th>
                                            <th>Company Name</th>
                                            <th>Contact Person</th>
                                            <th>Phone</th>
                                            <th>Email</th>
                                            <th>Credit Limit</th>
                                            <th>Balance</th>
                                            <th>Terms</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (isset($companies) && count($companies) > 0) { ?>
                                            <?php foreach ($companies as $c) { ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($c->company_code); ?></td>
                                                <td><strong><?php echo htmlspecialchars($c->company_name); ?></strong></td>
                                                <td><?php echo htmlspecialchars($c->contact_person); ?></td>
                                                <td><?php echo htmlspecialchars($c->phone); ?></td>
                                                <td><?php echo htmlspecialchars($c->email); ?></td>
                                                <td class="text-right">GHS <?php echo number_format($c->credit_limit, 2); ?></td>
                                                <td class="text-right">GHS <?php echo number_format($c->current_balance, 2); ?></td>
                                                <td><?php echo htmlspecialchars($c->payment_terms); ?></td>
                                                <td>
                                                    <button class="btn btn-xs btn-info btn-pricing" data-id="<?php echo $c->id; ?>" data-name="<?php echo htmlspecialchars($c->company_name); ?>">
                                                        <i class="fa fa-money"></i> Pricing
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php } ?>
                                        <?php } else { ?>
                                            <tr><td colspan="9" class="text-center text-muted">No companies registered</td></tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pricing Rules Info -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="box box-info collapsed-box">
                            <div class="box-header with-border">
                                <h3 class="box-title"><i class="fa fa-info-circle"></i> Pricing Rules Guide</h3>
                                <div class="box-tools pull-right">
                                    <button class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-plus"></i></button>
                                </div>
                            </div>
                            <div class="box-body">
                                <h4>Pricing Types</h4>
                                <ul>
                                    <li><strong>FIXED:</strong> Set a fixed price for services (e.g., GHS 15.00 for all lab tests)</li>
                                    <li><strong>PERCENT:</strong> Add a percentage markup to base price (e.g., 10% increase)</li>
                                    <li><strong>DISCOUNT:</strong> Apply a percentage discount (e.g., 15% off)</li>
                                </ul>
                                <h4>Pricing Priority</h4>
                                <ol>
                                    <li>Specific service pricing (e.g., FBC test for Company A)</li>
                                    <li>Service type pricing (e.g., all LAB tests for Company A)</li>
                                    <li>Global company pricing (e.g., all services for Company A)</li>
                                    <li>Default pricing (base price from system)</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </aside>
    </div>

    <!-- Add Company Modal -->
    <div class="modal fade" id="addCompanyModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-green">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title"><i class="fa fa-building"></i> Add Corporate Company</h4>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Company Name *</label>
                        <input type="text" id="company_name" class="form-control" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Company Code</label>
                                <input type="text" id="company_code" class="form-control" placeholder="e.g., COMP001">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Payment Terms</label>
                                <select id="payment_terms" class="form-control">
                                    <option value="NET30">NET 30</option>
                                    <option value="NET60">NET 60</option>
                                    <option value="NET90">NET 90</option>
                                    <option value="IMMEDIATE">Immediate</option>
                                </select>
                            </div>
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
                        <label>Credit Limit (GHS)</label>
                        <input type="number" id="credit_limit" class="form-control" value="0" step="0.01">
                    </div>
                    <div class="form-group">
                        <label>Address</label>
                        <textarea id="address" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="btnAddCompany"><i class="fa fa-save"></i> Save Company</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Pricing Modal -->
    <div class="modal fade" id="pricingModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-blue">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title"><i class="fa fa-money"></i> Set Company Pricing</h4>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="pricing_company_id">
                    <p><strong>Company:</strong> <span id="pricing_company_name"></span></p>
                    <div class="form-group">
                        <label>Service Type (leave blank for all)</label>
                        <select id="pricing_service_type" class="form-control">
                            <option value="">All Services</option>
                            <option value="LAB">Laboratory Tests</option>
                            <option value="SONOGRAPHY">Sonography/Radiology</option>
                            <option value="PROCEDURE">Procedures</option>
                            <option value="MEDICATION">Medications</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Pricing Type</label>
                        <select id="pricing_type" class="form-control">
                            <option value="PERCENT">Percentage Markup</option>
                            <option value="DISCOUNT">Percentage Discount</option>
                            <option value="FIXED">Fixed Price</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Value</label>
                        <div class="input-group">
                            <input type="number" id="pricing_value" class="form-control" value="0" step="0.01">
                            <span class="input-group-addon" id="pricing_suffix">%</span>
                        </div>
                        <small class="text-muted" id="pricing_help">Enter percentage (e.g., 10 for 10% markup)</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="btnSavePricing"><i class="fa fa-save"></i> Save Pricing Rule</button>
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
        $('#tblCompanies').DataTable({"pageLength": 25});

        // Add company
        $('#btnAddCompany').click(function() {
            var name = $('#company_name').val().trim();
            if (!name) {
                alert('Company name is required');
                return;
            }
            
            var btn = $(this);
            btn.prop('disabled', true);
            
            var companyData = {
                company_name: name,
                company_code: $('#company_code').val(),
                contact_person: $('#contact_person').val(),
                phone: $('#phone').val(),
                email: $('#email').val(),
                address: $('#address').val(),
                credit_limit: $('#credit_limit').val(),
                payment_terms: $('#payment_terms').val()
            };
            companyData[csrfName] = csrfHash;
            $.post('<?php echo base_url(); ?>app/service_queue/add_company', companyData, function(resp) {
                if (resp.success) {
                    alert('Company added successfully');
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

        // Open pricing modal
        $('.btn-pricing').click(function() {
            $('#pricing_company_id').val($(this).data('id'));
            $('#pricing_company_name').text($(this).data('name'));
            $('#pricingModal').modal('show');
        });

        // Update pricing help text
        $('#pricing_type').change(function() {
            var type = $(this).val();
            if (type === 'FIXED') {
                $('#pricing_suffix').text('GHS');
                $('#pricing_help').text('Enter fixed price in GHS');
            } else if (type === 'DISCOUNT') {
                $('#pricing_suffix').text('%');
                $('#pricing_help').text('Enter discount percentage (e.g., 15 for 15% off)');
            } else {
                $('#pricing_suffix').text('%');
                $('#pricing_help').text('Enter markup percentage (e.g., 10 for 10% increase)');
            }
        });

        // Save pricing
        $('#btnSavePricing').click(function() {
            var btn = $(this);
            btn.prop('disabled', true);
            
            var pricingData = {
                company_id: $('#pricing_company_id').val(),
                service_type: $('#pricing_service_type').val(),
                pricing_type: $('#pricing_type').val(),
                value: $('#pricing_value').val()
            };
            pricingData[csrfName] = csrfHash;
            $.post('<?php echo base_url(); ?>app/service_queue/set_company_pricing', pricingData, function(resp) {
                if (resp.success) {
                    alert('Pricing rule saved');
                    $('#pricingModal').modal('hide');
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
