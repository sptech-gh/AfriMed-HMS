<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Hebrew Medical Center — Create Return</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/datatables/dataTables.bootstrap.css" rel="stylesheet" type="text/css" />
    <style>
        .dispense-card { border: 1px solid #ddd; border-radius: 5px; padding: 15px; margin-bottom: 10px; background: #f9f9f9; }
        .dispense-card:hover { background: #e8f4fc; border-color: #3c8dbc; cursor: pointer; }
        .dispense-card.selected { background: #d4edda; border-color: #28a745; }
        .drug-name { font-weight: bold; font-size: 14px; }
        .drug-details { color: #666; font-size: 12px; }
        .qty-info { font-size: 16px; font-weight: bold; color: #3c8dbc; }
        .returnable { color: #28a745; }
    </style>
</head>
<body class="skin-blue">
    <?php require_once(APPPATH.'views/include/header.php');?>
    <div class="wrapper row-offcanvas row-offcanvas-left">
        <?php require_once(APPPATH.'views/include/sidebar.php');?>
        <aside class="right-side">
            <section class="content-header">
                <h1><i class="fa fa-undo"></i> Create Return <small>Request drug return</small></h1>
                <ol class="breadcrumb">
                    <li><a href="<?php echo base_url()?>app/pharmacy"><i class="fa fa-medkit"></i> Pharmacy</a></li>
                    <li><a href="<?php echo base_url()?>app/pharmacy/pharmacy_returns">Returns</a></li>
                    <li class="active">Create</li>
                </ol>
            </section>

            <section class="content">
                <?php if(isset($message) && $message){ echo $message; } ?>

                <div class="row">
                    <!-- Search Panel -->
                    <div class="col-md-7">
                        <div class="box box-primary">
                            <div class="box-header with-border">
                                <h3 class="box-title"><i class="fa fa-search"></i> Find Dispensed Items</h3>
                            </div>
                            <div class="box-body">
                                <form method="get" class="form-inline" style="margin-bottom: 15px;">
                                    <div class="form-group">
                                        <input type="text" name="search" id="searchInput" class="form-control" 
                                               placeholder="Search patient, drug..." style="width: 300px;"
                                               value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                                    </div>
                                    <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> Search</button>
                                </form>

                                <div id="dispenseList">
                                    <?php if(!empty($dispensed_items)): ?>
                                    <?php foreach($dispensed_items as $item): 
                                        $returnable = (float)$item->dose_given - (float)$item->already_returned;
                                        if ($returnable <= 0) continue;
                                    ?>
                                    <div class="dispense-card" data-admin-id="<?php echo $item->admin_id; ?>"
                                         data-drug-name="<?php echo htmlspecialchars($item->drug_name); ?>"
                                         data-patient="<?php echo htmlspecialchars($item->patient_name); ?>"
                                         data-patient-no="<?php echo htmlspecialchars($item->patient_no); ?>"
                                         data-dispensed="<?php echo $item->dose_given; ?>"
                                         data-returnable="<?php echo $returnable; ?>"
                                         data-batch-no="<?php echo isset($item->batch_no) ? htmlspecialchars($item->batch_no) : ''; ?>"
                                         data-dispense-date="<?php echo $item->dispense_date; ?>">
                                        <div class="row">
                                            <div class="col-md-8">
                                                <div class="drug-name"><?php echo htmlspecialchars($item->drug_name); ?></div>
                                                <div class="drug-details">
                                                    <?php if(!empty($item->generic_name)): ?>
                                                    <i class="fa fa-tag"></i> <?php echo htmlspecialchars($item->generic_name); ?><br>
                                                    <?php endif; ?>
                                                    <i class="fa fa-user"></i> <?php echo htmlspecialchars($item->patient_name); ?> 
                                                    <span class="text-muted">(<?php echo htmlspecialchars($item->patient_no); ?>)</span><br>
                                                    <i class="fa fa-calendar"></i> Dispensed: <?php echo date('d M Y H:i', strtotime($item->dispense_date)); ?>
                                                </div>
                                            </div>
                                            <div class="col-md-4 text-right">
                                                <div class="qty-info">
                                                    Dispensed: <?php echo number_format($item->dose_given, 0); ?>
                                                </div>
                                                <div class="qty-info returnable">
                                                    Returnable: <?php echo number_format($returnable, 0); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                    <?php else: ?>
                                    <div class="alert alert-info">
                                        <i class="fa fa-info-circle"></i> Search for dispensed items to create a return.
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Return Form -->
                    <div class="col-md-5">
                        <div class="box box-success">
                            <div class="box-header with-border">
                                <h3 class="box-title"><i class="fa fa-undo"></i> Return Details</h3>
                            </div>
                            <form method="post" action="<?php echo base_url(); ?>app/pharmacy/save_return" id="returnForm">
                                <div class="box-body">
                                    <input type="hidden" name="admin_id" id="admin_id" required>
                                    
                                    <div class="form-group">
                                        <label>Selected Item</label>
                                        <div id="selectedItem" class="well well-sm" style="min-height: 60px;">
                                            <span class="text-muted">Click on a dispensed item to select</span>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label>Quantity to Return <span class="text-danger">*</span></label>
                                        <input type="number" name="quantity_returned" id="quantity_returned" 
                                               class="form-control" min="1" step="1" required disabled>
                                        <span class="help-block" id="maxQtyHelp"></span>
                                    </div>

                                    <div class="form-group">
                                        <label>Return Type <span class="text-danger">*</span></label>
                                        <select name="return_type" id="return_type" class="form-control" required disabled>
                                            <?php if(!empty($return_types)): foreach($return_types as $key => $label): ?>
                                            <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                                            <?php endforeach; else: ?>
                                            <option value="PATIENT_RETURN">Patient Return</option>
                                            <option value="WARD_RETURN">Ward Return</option>
                                            <option value="INTERNAL_CORRECTION">Internal Correction</option>
                                            <?php endif; ?>
                                        </select>
                                    </div>

                                    <div class="form-group">
                                        <label>Return Reason <span class="text-danger">*</span></label>
                                        <select name="return_reason" id="return_reason" class="form-control" required disabled>
                                            <option value="">-- Select Reason --</option>
                                            <?php if(!empty($return_reasons)): foreach($return_reasons as $key => $label): ?>
                                            <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                                            <?php endforeach; endif; ?>
                                        </select>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Batch No.</label>
                                                <input type="text" name="batch_no" id="batch_no" class="form-control" 
                                                       placeholder="Batch number" disabled>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Stock Location</label>
                                                <select name="stock_location" id="stock_location" class="form-control" disabled>
                                                    <?php if(!empty($stock_locations)): foreach($stock_locations as $key => $label): ?>
                                                    <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                                                    <?php endforeach; else: ?>
                                                    <option value="MAIN_PHARMACY">Main Pharmacy</option>
                                                    <option value="WARD_STOCK">Ward Stock</option>
                                                    <option value="EMERGENCY">Emergency</option>
                                                    <?php endif; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label>Notes</label>
                                        <textarea name="return_notes" id="return_notes" class="form-control" 
                                                  rows="3" placeholder="Additional notes..." disabled></textarea>
                                    </div>
                                    
                                    <div id="windowWarning" class="alert alert-warning" style="display:none;">
                                        <i class="fa fa-exclamation-triangle"></i> 
                                        <strong>Return Window Exceeded:</strong> This item was dispensed more than 
                                        <?php echo isset($return_window_hours) ? $return_window_hours : 48; ?> hours ago.
                                        <?php if($this->session->userdata('role') === 'admin'): ?>
                                        <br><small>As admin, you can still process this return.</small>
                                        <?php else: ?>
                                        <br><small>Admin approval will be required.</small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="box-footer">
                                    <a href="<?php echo base_url(); ?>app/pharmacy/pharmacy_returns" class="btn btn-default">
                                        <i class="fa fa-arrow-left"></i> Back
                                    </a>
                                    <button type="submit" class="btn btn-success pull-right" id="submitBtn" disabled>
                                        <i class="fa fa-save"></i> Submit Return Request
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

            </section>
        </aside>
    </div>

    <script src="<?php echo base_url();?>public/js/jquery.min.js"></script>
    <script src="<?php echo base_url();?>public/js/bootstrap.min.js"></script>
    <script src="<?php echo base_url();?>public/js/AdminLTE/app.js"></script>
    <script>
    $(function(){
        var returnWindowHours = <?php echo isset($return_window_hours) ? $return_window_hours : 48; ?>;
        
        // Select dispense card
        $('.dispense-card').click(function(){
            $('.dispense-card').removeClass('selected');
            $(this).addClass('selected');

            var adminId = $(this).data('admin-id');
            var drugName = $(this).data('drug-name');
            var patient = $(this).data('patient');
            var patientNo = $(this).data('patient-no');
            var dispensed = $(this).data('dispensed');
            var returnable = $(this).data('returnable');
            var batchNo = $(this).data('batch-no') || '';
            var dispenseDate = $(this).data('dispense-date');

            $('#admin_id').val(adminId);
            $('#selectedItem').html(
                '<strong>' + drugName + '</strong><br>' +
                '<small class="text-muted">' + patient + ' (' + patientNo + ')</small><br>' +
                '<span class="label label-info">Dispensed: ' + dispensed + '</span> ' +
                '<span class="label label-success">Returnable: ' + returnable + '</span>' +
                (batchNo ? ' <span class="label label-default">Batch: ' + batchNo + '</span>' : '')
            );

            $('#quantity_returned').attr('max', returnable).val('').prop('disabled', false);
            $('#maxQtyHelp').text('Maximum: ' + returnable + ' units');
            $('#return_type').prop('disabled', false);
            $('#return_reason').prop('disabled', false);
            $('#batch_no').val(batchNo).prop('disabled', false);
            $('#stock_location').prop('disabled', false);
            $('#return_notes').prop('disabled', false);
            $('#submitBtn').prop('disabled', false);
            
            // Check return window
            if (dispenseDate) {
                var dispenseTime = new Date(dispenseDate).getTime();
                var now = new Date().getTime();
                var hoursElapsed = (now - dispenseTime) / (1000 * 60 * 60);
                
                if (hoursElapsed > returnWindowHours) {
                    $('#windowWarning').show();
                } else {
                    $('#windowWarning').hide();
                }
            }
        });

        // Form validation
        $('#returnForm').submit(function(e){
            var adminId = $('#admin_id').val();
            var qty = $('#quantity_returned').val();
            var reason = $('#return_reason').val();

            if (!adminId) {
                alert('Please select a dispensed item');
                e.preventDefault();
                return false;
            }
            if (!qty || qty <= 0) {
                alert('Please enter a valid quantity');
                e.preventDefault();
                return false;
            }
            if (!reason) {
                alert('Please select a return reason');
                e.preventDefault();
                return false;
            }
            return true;
        });
    });
    </script>
</body>
</html>
