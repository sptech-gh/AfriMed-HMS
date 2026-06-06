<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Hebrew Medical Center — NHIS Submission Queue</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/datatables/dataTables.bootstrap.css" rel="stylesheet" type="text/css" />
</head>
<body class="skin-blue">
    <?php require_once(APPPATH.'views/include/header.php');?>
    <div class="wrapper row-offcanvas row-offcanvas-left">
        <?php require_once(APPPATH.'views/include/sidebar.php');?>
        <aside class="right-side">
            <section class="content-header">
                <h1><i class="fa fa-list"></i> Submission Queue <small>Claims ready for NHIS submission</small></h1>
                <ol class="breadcrumb">
                    <li><a href="<?php echo base_url()?>app/nhis_claims/claimit"><i class="fa fa-cloud-upload"></i> Claim-IT</a></li>
                    <li class="active">Submission Queue</li>
                </ol>
            </section>

            <section class="content">
                <?php if(isset($message) && $message){ echo $message; } ?>

                <!-- Ready Claims -->
                <div class="box box-success">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-check-circle"></i> Ready for Submission (<?php echo count($ready_claims ?? []); ?>)</h3>
                        <div class="box-tools">
                            <button type="button" id="submitAll" class="btn btn-sm btn-success" <?php echo empty($ready_claims) ? 'disabled' : ''; ?>>
                                <i class="fa fa-cloud-upload"></i> Submit All
                            </button>
                        </div>
                    </div>
                    <div class="box-body">
                        <table class="table table-bordered table-striped" id="readyTable">
                            <thead>
                                <tr>
                                    <th><input type="checkbox" id="selectAll"></th>
                                    <th>Claim #</th>
                                    <th>Patient</th>
                                    <th>NHIS #</th>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(!empty($ready_claims)): foreach($ready_claims as $c): ?>
                                <tr>
                                    <td><input type="checkbox" class="claim-check" value="<?php echo $c->id; ?>"></td>
                                    <td><strong><?php echo htmlspecialchars($c->claim_number); ?></strong></td>
                                    <td><?php echo htmlspecialchars($c->patient_no); ?></td>
                                    <td><?php echo htmlspecialchars($c->nhis_number ?? '-'); ?></td>
                                    <td><?php echo date('d M Y', strtotime($c->service_date ?? $c->created_at)); ?></td>
                                    <td class="text-right">GHS <?php echo number_format($c->total_amount, 2); ?></td>
                                    <td>
                                        <a href="<?php echo base_url()?>app/nhis_claims/claimit_view/<?php echo $c->id; ?>" class="btn btn-xs btn-info"><i class="fa fa-eye"></i></a>
                                        <a href="<?php echo base_url()?>app/nhis_claims/claimit_submit/<?php echo $c->id; ?>" class="btn btn-xs btn-success"><i class="fa fa-cloud-upload"></i> Submit</a>
                                    </td>
                                </tr>
                                <?php endforeach; else: ?>
                                <tr><td colspan="7" class="text-center text-muted">No claims ready for submission</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Submitted Claims -->
                <div class="box box-info">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-cloud"></i> Recently Submitted (<?php echo count($submitted_claims ?? []); ?>)</h3>
                    </div>
                    <div class="box-body">
                        <table class="table table-bordered table-striped" id="submittedTable">
                            <thead>
                                <tr>
                                    <th>Claim #</th>
                                    <th>Claim-IT Ref</th>
                                    <th>Patient</th>
                                    <th>Amount</th>
                                    <th>Submitted</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(!empty($submitted_claims)): foreach($submitted_claims as $c): ?>
                                <tr>
                                    <td><a href="<?php echo base_url()?>app/nhis_claims/claimit_view/<?php echo $c->id; ?>"><?php echo htmlspecialchars($c->claim_number); ?></a></td>
                                    <td><code><?php echo htmlspecialchars($c->claimit_reference ?? '-'); ?></code></td>
                                    <td><?php echo htmlspecialchars($c->patient_no); ?></td>
                                    <td class="text-right">GHS <?php echo number_format($c->total_amount, 2); ?></td>
                                    <td><?php echo $c->submitted_at ? date('d M Y H:i', strtotime($c->submitted_at)) : '-'; ?></td>
                                    <td><span class="label label-primary"><?php echo $c->status; ?></span></td>
                                </tr>
                                <?php endforeach; else: ?>
                                <tr><td colspan="6" class="text-center text-muted">No recently submitted claims</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </section>
        </aside>
    </div>

    <script src="<?php echo base_url();?>public/js/jquery.min.js"></script>
    <script src="<?php echo base_url();?>public/js/bootstrap.min.js"></script>
    <script src="<?php echo base_url();?>public/js/AdminLTE/app.js"></script>
    <script src="<?php echo base_url();?>public/js/plugins/datatables/jquery.dataTables.js"></script>
    <script src="<?php echo base_url();?>public/js/plugins/datatables/dataTables.bootstrap.js"></script>
    
<script>
$(function(){
    // Check if DataTable is already initialized before creating new instance
    var readyTable, submittedTable;

    // Initialize readyTable
    if (!$.fn.dataTable.isDataTable('#readyTable')) {
        readyTable = $("#readyTable").DataTable({
            "pageLength": 25,
            "columnDefs": [
                { "targets": 0, "orderable": false, "searchable": false },
                { "targets": 6, "orderable": false, "searchable": false }
            ],
            "autoWidth": false,
            "language": {
                "emptyTable": "No claims ready for submission",
                "zeroRecords": "No claims ready for submission"
            }
        });
    } else {
        readyTable = $("#readyTable").DataTable();
    }

    // Initialize submittedTable
    if (!$.fn.dataTable.isDataTable('#submittedTable')) {
        submittedTable = $("#submittedTable").DataTable({
            "pageLength": 25,
            "autoWidth": false,
            "language": {
                "emptyTable": "No recently submitted claims",
                "zeroRecords": "No recently submitted claims"
            }
        });
    } else {
        submittedTable = $("#submittedTable").DataTable();
    }

    // Select all checkbox handler
    $("#selectAll").change(function(){
        $(".claim-check").prop("checked", $(this).is(":checked"));
    });

    // Submit all button handler
    $("#submitAll").click(function(){
        var ids = [];
        $(".claim-check:checked").each(function(){ ids.push($(this).val()); });
        if(ids.length === 0){ alert("Select at least one claim"); return; }
        if(!confirm("Submit " + ids.length + " claims to NHIS?")) return;
        alert("Batch submission not yet implemented. Submit individually.");
    });
});
</script>
</body>
</html>
