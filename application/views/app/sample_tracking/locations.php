<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo $title; ?> - HMS</title>
    <meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>
    <link href="<?php echo base_url(); ?>public/bootstrap/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/font-awesome/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/dist/css/AdminLTE.css" rel="stylesheet" type="text/css" />
    <style>
        .location-card { border: 1px solid #ddd; border-radius: 8px; padding: 15px; margin-bottom: 15px; background: #fff; }
        .location-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .location-card.inactive { opacity: 0.6; background: #f5f5f5; }
        .location-type { padding: 3px 8px; border-radius: 3px; font-size: 11px; font-weight: bold; }
        .type-COLLECTION { background: #3498db; color: #fff; }
        .type-TRANSPORT { background: #f39c12; color: #fff; }
        .type-LABORATORY { background: #9b59b6; color: #fff; }
        .type-STORAGE { background: #1abc9c; color: #fff; }
        .type-PROCESSING { background: #27ae60; color: #fff; }
        .type-DISPOSAL { background: #e74c3c; color: #fff; }
    </style>
</head>
<body class="skin-blue">
<?php require_once(APPPATH . 'views/include/header.php'); ?>
<div class="wrapper row-offcanvas row-offcanvas-left">
    <?php require_once(APPPATH . 'views/include/sidebar.php'); ?>
    <aside class="right-side">
        <section class="content-header">
            <h1><i class="fa fa-map-marker"></i> Sample Locations</h1>
            <ol class="breadcrumb">
                <li><a href="<?php echo base_url(); ?>app/sample_tracking"><i class="fa fa-flask"></i> Sample Tracking</a></li>
                <li class="active">Locations</li>
            </ol>
        </section>

        <section class="content">
            <div class="row">
                <div class="col-md-12">
                    <div class="box box-primary">
                        <div class="box-header with-border">
                            <h3 class="box-title"><i class="fa fa-list"></i> Sample Tracking Locations</h3>
                            <div class="box-tools">
                                <button class="btn btn-sm btn-success" id="btnAddLocation">
                                    <i class="fa fa-plus"></i> Add Location
                                </button>
                            </div>
                        </div>
                        <div class="box-body">
                            <div class="row">
                                <?php if (empty($locations)): ?>
                                <div class="col-md-12">
                                    <div class="alert alert-info">
                                        <i class="fa fa-info-circle"></i> No locations configured. Click "Add Location" to create one.
                                    </div>
                                </div>
                                <?php else: foreach ($locations as $loc): ?>
                                <div class="col-md-4">
                                    <div class="location-card <?php echo $loc->is_active ? '' : 'inactive'; ?>">
                                        <div class="row">
                                            <div class="col-xs-8">
                                                <h4 class="mt-0 mb-0"><?php echo htmlspecialchars($loc->location_name); ?></h4>
                                                <code><?php echo htmlspecialchars($loc->location_code); ?></code>
                                            </div>
                                            <div class="col-xs-4 text-right">
                                                <span class="location-type type-<?php echo $loc->location_type; ?>">
                                                    <?php echo $loc->location_type; ?>
                                                </span>
                                            </div>
                                        </div>
                                        <hr>
                                        <table class="table table-condensed mb-0">
                                            <?php if ($loc->department): ?>
                                            <tr><td><i class="fa fa-building"></i> Department</td><td><?php echo htmlspecialchars($loc->department); ?></td></tr>
                                            <?php endif; ?>
                                            <?php if ($loc->building): ?>
                                            <tr><td><i class="fa fa-home"></i> Building</td><td><?php echo htmlspecialchars($loc->building); ?></td></tr>
                                            <?php endif; ?>
                                            <?php if ($loc->floor): ?>
                                            <tr><td><i class="fa fa-level-up"></i> Floor</td><td><?php echo htmlspecialchars($loc->floor); ?></td></tr>
                                            <?php endif; ?>
                                            <?php if ($loc->room): ?>
                                            <tr><td><i class="fa fa-door-open"></i> Room</td><td><?php echo htmlspecialchars($loc->room); ?></td></tr>
                                            <?php endif; ?>
                                            <?php if ($loc->temperature_required !== null): ?>
                                            <tr><td><i class="fa fa-thermometer-half"></i> Temp Required</td><td><?php echo $loc->temperature_required; ?>°C ±<?php echo $loc->temperature_tolerance ?? 2; ?>°C</td></tr>
                                            <?php endif; ?>
                                        </table>
                                        <div class="mt-2">
                                            <button class="btn btn-xs btn-primary btn-edit" 
                                                    data-location='<?php echo json_encode($loc); ?>'>
                                                <i class="fa fa-edit"></i> Edit
                                            </button>
                                            <?php if ($loc->is_active): ?>
                                            <span class="label label-success pull-right">Active</span>
                                            <?php else: ?>
                                            <span class="label label-default pull-right">Inactive</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </aside>
</div>

<!-- Location Modal -->
<div class="modal fade" id="locationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="fa fa-map-marker"></i> <span id="modalTitle">Add Location</span></h4>
            </div>
            <div class="modal-body">
                <form id="locationForm">
                    <input type="hidden" id="location_id" name="location_id">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Location Code <span class="text-danger">*</span></label>
                                <input type="text" id="location_code" name="location_code" class="form-control" required placeholder="e.g., LAB-MAIN">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Location Name <span class="text-danger">*</span></label>
                                <input type="text" id="location_name" name="location_name" class="form-control" required placeholder="e.g., Main Laboratory">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Location Type <span class="text-danger">*</span></label>
                                <select id="location_type" name="location_type" class="form-control" required>
                                    <option value="">-- Select --</option>
                                    <option value="COLLECTION">Collection Point</option>
                                    <option value="TRANSPORT">Transport/Transit</option>
                                    <option value="LABORATORY">Laboratory</option>
                                    <option value="STORAGE">Storage</option>
                                    <option value="PROCESSING">Processing Area</option>
                                    <option value="DISPOSAL">Disposal</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Department</label>
                                <input type="text" id="department" name="department" class="form-control" placeholder="e.g., Pathology">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Building</label>
                                <input type="text" id="building" name="building" class="form-control" placeholder="e.g., Main Block">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Floor</label>
                                <input type="text" id="floor" name="floor" class="form-control" placeholder="e.g., Ground">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Room</label>
                                <input type="text" id="room" name="room" class="form-control" placeholder="e.g., Room 101">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Required Temperature (°C)</label>
                                <input type="number" step="0.1" id="temperature_required" name="temperature_required" class="form-control" placeholder="e.g., 4">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Temperature Tolerance (±°C)</label>
                                <input type="number" step="0.1" id="temperature_tolerance" name="temperature_tolerance" class="form-control" placeholder="e.g., 2">
                            </div>
                        </div>
                    </div>
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" id="is_active" name="is_active" value="1" checked> Active
                        </label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="btnSaveLocation"><i class="fa fa-save"></i> Save Location</button>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo base_url(); ?>public/jquery/jquery.min.js"></script>
<script src="<?php echo base_url(); ?>public/bootstrap/js/bootstrap.min.js"></script>
<script src="<?php echo base_url(); ?>public/dist/js/app.min.js"></script>
<script>
$(document).ready(function() {
    function resetForm() {
        $('#locationForm')[0].reset();
        $('#location_id').val('');
        $('#modalTitle').text('Add Location');
        $('#is_active').prop('checked', true);
    }

    $('#btnAddLocation').click(function() {
        resetForm();
        $('#locationModal').modal('show');
    });

    $('.btn-edit').click(function() {
        var loc = $(this).data('location');
        $('#modalTitle').text('Edit Location');
        $('#location_id').val(loc.location_id);
        $('#location_code').val(loc.location_code);
        $('#location_name').val(loc.location_name);
        $('#location_type').val(loc.location_type);
        $('#department').val(loc.department);
        $('#building').val(loc.building);
        $('#floor').val(loc.floor);
        $('#room').val(loc.room);
        $('#temperature_required').val(loc.temperature_required);
        $('#temperature_tolerance').val(loc.temperature_tolerance);
        $('#is_active').prop('checked', loc.is_active == 1);
        $('#locationModal').modal('show');
    });

    $('#btnSaveLocation').click(function() {
        var csrfName = '<?php echo $this->security->get_csrf_token_name(); ?>';
        var csrfHash = '<?php echo $this->security->get_csrf_hash(); ?>';
        var data = {
            location_id: $('#location_id').val(),
            location_code: $('#location_code').val(),
            location_name: $('#location_name').val(),
            location_type: $('#location_type').val(),
            department: $('#department').val(),
            building: $('#building').val(),
            floor: $('#floor').val(),
            room: $('#room').val(),
            temperature_required: $('#temperature_required').val(),
            temperature_tolerance: $('#temperature_tolerance').val(),
            is_active: $('#is_active').is(':checked') ? 1 : 0
        };
        data[csrfName] = csrfHash;

        if (!data.location_code || !data.location_name || !data.location_type) {
            alert('Please fill all required fields');
            return;
        }

        $.post('<?php echo base_url(); ?>app/sample_tracking/save_location', data, function(resp) {
            if (resp.ok) {
                alert('Location saved successfully');
                location.reload();
            } else {
                alert('Error saving location');
            }
        }, 'json');
    });
});
</script>
</body>
</html>
