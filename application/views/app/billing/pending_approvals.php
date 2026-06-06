<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Pending Approvals - Hebrew Medical Center</title>
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
                <h1><i class="fa fa-gavel"></i> Pending Approvals <small>Billing Exception Requests</small></h1>
                <ol class="breadcrumb">
                    <li><a href="<?php echo base_url() ?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                    <li><a href="<?php echo base_url() ?>app/service_queue">Service Queue</a></li>
                    <li class="active">Pending Approvals</li>
                </ol>
            </section>

            <section class="content">
                <?php if ($this->session->flashdata('message')) { echo $this->session->flashdata('message'); } ?>

                <div class="row">
                    <div class="col-md-12">
                        <div class="box box-warning">
                            <div class="box-header with-border">
                                <h3 class="box-title"><i class="fa fa-clock-o"></i> Pending Approval Requests</h3>
                            </div>
                            <div class="box-body table-responsive">
                                <table id="tblApprovals" class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Patient</th>
                                            <th>Service</th>
                                            <th>Type</th>
                                            <th>Original Amount</th>
                                            <th>Reason</th>
                                            <th>Requested By</th>
                                            <th>Date</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (isset($approvals) && count($approvals) > 0) { ?>
                                            <?php foreach ($approvals as $a) { ?>
                                            <tr>
                                                <td><?php echo $a->id; ?></td>
                                                <td>
                                                    <?php echo htmlspecialchars(trim($a->firstname . ' ' . $a->lastname)); ?>
                                                    <br><small class="text-muted"><?php echo $a->patient_no; ?></small>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($a->service_name); ?>
                                                    <br><small class="label label-default"><?php echo $a->service_type; ?></small>
                                                </td>
                                                <td>
                                                    <?php
                                                    $typeClass = 'label-default';
                                                    switch ($a->approval_type) {
                                                        case 'WAIVE': $typeClass = 'label-danger'; break;
                                                        case 'DEFER': $typeClass = 'label-warning'; break;
                                                        case 'EMERGENCY': $typeClass = 'label-info'; break;
                                                        case 'CREDIT': $typeClass = 'label-primary'; break;
                                                    }
                                                    ?>
                                                    <span class="label <?php echo $typeClass; ?>"><?php echo $a->approval_type; ?></span>
                                                </td>
                                                <td class="text-right">GHS <?php echo number_format($a->original_amount, 2); ?></td>
                                                <td><?php echo htmlspecialchars($a->reason); ?></td>
                                                <td><?php echo $a->requested_by; ?></td>
                                                <td><?php echo date('M d, H:i', strtotime($a->created_at)); ?></td>
                                                <td>
                                                    <button class="btn btn-xs btn-success btn-approve" data-id="<?php echo $a->id; ?>">
                                                        <i class="fa fa-check"></i> Approve
                                                    </button>
                                                    <button class="btn btn-xs btn-danger btn-reject" data-id="<?php echo $a->id; ?>">
                                                        <i class="fa fa-times"></i> Reject
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php } ?>
                                        <?php } else { ?>
                                            <tr><td colspan="9" class="text-center text-success"><i class="fa fa-check"></i> No pending approval requests</td></tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </aside>
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
        $('#tblApprovals').DataTable({"pageLength": 25});

        // Approve
        $('.btn-approve').click(function() {
            var id = $(this).data('id');
            if (confirm('Approve this billing exception?')) {
                var approveData = { approval_id: id };
                approveData[csrfName] = csrfHash;
                $.post('<?php echo base_url(); ?>app/service_queue/approve_exception', approveData, function(resp) {
                    if (resp.success) {
                        alert('Approved successfully');
                        location.reload();
                    } else {
                        alert('Error: ' + resp.error);
                    }
                }, 'json');
            }
        });

        // Reject
        $('.btn-reject').click(function() {
            var id = $(this).data('id');
            if (confirm('Reject this billing exception request?')) {
                var rejectData = { approval_id: id };
                rejectData[csrfName] = csrfHash;
                $.post('<?php echo base_url(); ?>app/service_queue/reject_exception', rejectData, function(resp) {
                    if (resp.success) {
                        alert('Rejected');
                        location.reload();
                    } else {
                        alert('Error: ' + resp.error);
                    }
                }, 'json');
            }
        });
    });
    </script>
</body>
</html>
