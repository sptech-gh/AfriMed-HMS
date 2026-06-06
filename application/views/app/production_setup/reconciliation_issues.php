<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Reconciliation Issues - HMS</title>
    <meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>
    <link href="<?php echo base_url(); ?>public/bootstrap/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/font-awesome/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/dist/css/AdminLTE.css" rel="stylesheet" type="text/css" />
</head>
<body class="skin-blue">
<?php require_once(APPPATH.'views/include/header.php');?>
<div class="wrapper row-offcanvas row-offcanvas-left">
    <?php require_once(APPPATH.'views/include/sidebar.php');?>
    <aside class="right-side">
        <section class="content-header">
            <h1><i class="fa fa-exclamation-triangle"></i> Reconciliation Issues</h1>
        </section>
        <section class="content">
            <div class="box box-warning">
                <div class="box-header with-border">
                    <h3 class="box-title">Unresolved Issues (<?php echo count($issues); ?>)</h3>
                </div>
                <div class="box-body">
                    <?php if (empty($issues)): ?>
                        <div class="alert alert-success">
                            <i class="fa fa-check"></i> No unresolved issues found.
                        </div>
                    <?php else: ?>
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Department</th>
                                <th>Severity</th>
                                <th>Patient</th>
                                <th>Description</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($issues as $issue): ?>
                            <tr>
                                <td><?php echo date('Y-m-d', strtotime($issue->issue_date)); ?></td>
                                <td><code><?php echo $issue->issue_type; ?></code></td>
                                <td><?php echo $issue->department; ?></td>
                                <td>
                                    <?php 
                                    $sev_class = $issue->severity === 'HIGH' ? 'danger' : ($issue->severity === 'MEDIUM' ? 'warning' : 'info');
                                    ?>
                                    <span class="label label-<?php echo $sev_class; ?>"><?php echo $issue->severity; ?></span>
                                </td>
                                <td><?php echo $issue->patient_no; ?></td>
                                <td><?php echo htmlspecialchars($issue->description); ?></td>
                                <td>
                                    <button class="btn btn-xs btn-success" onclick="resolveIssue(<?php echo $issue->issue_id; ?>)">
                                        <i class="fa fa-check"></i> Resolve
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </aside>
</div>

<div class="modal fade" id="resolveModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Resolve Issue</h4>
            </div>
            <div class="modal-body">
                <input type="hidden" id="resolve_issue_id">
                <div class="form-group">
                    <label>Resolution Notes</label>
                    <textarea class="form-control" id="resolution_notes" rows="3" placeholder="Describe how this issue was resolved..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" onclick="submitResolve()">Mark Resolved</button>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo base_url(); ?>public/jquery/jquery.min.js"></script>
<script src="<?php echo base_url(); ?>public/bootstrap/js/bootstrap.min.js"></script>
<script src="<?php echo base_url(); ?>public/dist/js/app.js"></script>
<script>
function resolveIssue(id) {
    $('#resolve_issue_id').val(id);
    $('#resolution_notes').val('');
    $('#resolveModal').modal('show');
}

function submitResolve() {
    var csrfName = '<?php echo $this->security->get_csrf_token_name(); ?>';
    var csrfHash = '<?php echo $this->security->get_csrf_hash(); ?>';
    var postData = {
        issue_id: $('#resolve_issue_id').val(),
        resolution_notes: $('#resolution_notes').val()
    };
    postData[csrfName] = csrfHash;
    $.post('<?php echo base_url("app/production_setup/resolve_issue"); ?>', postData, function(data) {
        var res = JSON.parse(data);
        if (res.ok) {
            $('#resolveModal').modal('hide');
            location.reload();
        }
    });
}
</script>
</body>
</html>
