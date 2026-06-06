<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Hebrew Medical Center — Claim-IT API Logs</title>
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
                <h1><i class="fa fa-history"></i> API Logs <small>Claim-IT API call history</small></h1>
                <ol class="breadcrumb">
                    <li><a href="<?php echo base_url()?>app/nhis_claims/claimit"><i class="fa fa-cloud-upload"></i> Claim-IT</a></li>
                    <li class="active">API Logs</li>
                </ol>
            </section>

            <section class="content">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">Recent API Calls</h3>
                        <span class="badge bg-blue pull-right"><?php echo count($logs ?? []); ?> entries</span>
                    </div>
                    <div class="box-body">
                        <table class="table table-bordered table-striped" id="logsTable">
                            <thead>
                                <tr>
                                    <th>Timestamp</th>
                                    <th>Endpoint</th>
                                    <th>Status</th>
                                    <th>Duration</th>
                                    <th>Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(!empty($logs)): foreach($logs as $log): ?>
                                <tr>
                                    <td><?php echo date('d M Y H:i:s', strtotime($log->created_at)); ?></td>
                                    <td><code><?php echo htmlspecialchars($log->endpoint); ?></code></td>
                                    <td>
                                        <?php
                                        $statusCls = $log->status === 'SUCCESS' ? 'success' : ($log->status === 'ERROR' ? 'danger' : 'warning');
                                        ?>
                                        <span class="label label-<?php echo $statusCls; ?>"><?php echo $log->status; ?></span>
                                    </td>
                                    <td><?php echo isset($log->duration_ms) ? $log->duration_ms . 'ms' : '-'; ?></td>
                                    <td>
                                        <button type="button" class="btn btn-xs btn-info view-details" 
                                            data-request="<?php echo htmlspecialchars($log->request_payload ?? '{}'); ?>"
                                            data-response="<?php echo htmlspecialchars($log->response_payload ?? '{}'); ?>">
                                            <i class="fa fa-eye"></i> View
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </aside>
    </div>

    <!-- Details Modal -->
    <div class="modal fade" id="detailsModal">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title">API Call Details</h4>
                </div>
                <div class="modal-body">
                    <h5>Request</h5>
                    <pre id="requestJson" style="max-height:200px;overflow:auto;"></pre>
                    <h5>Response</h5>
                    <pre id="responseJson" style="max-height:200px;overflow:auto;"></pre>
                </div>
            </div>
        </div>
    </div>

    <script src="<?php echo base_url();?>public/js/jquery.min.js"></script>
    <script src="<?php echo base_url();?>public/js/bootstrap.min.js"></script>
    <script src="<?php echo base_url();?>public/js/AdminLTE/app.js"></script>
    <script src="<?php echo base_url();?>public/js/plugins/datatables/jquery.dataTables.js"></script>
    <script src="<?php echo base_url();?>public/js/plugins/datatables/dataTables.bootstrap.js"></script>
    <script>
    $(function(){
        $('#logsTable').DataTable({ 
            "order": [[0, "desc"]], 
            "pageLength": 50
        });
        
        $('.view-details').click(function(){
            var req = $(this).data('request');
            var res = $(this).data('response');
            try { req = JSON.stringify(JSON.parse(req), null, 2); } catch(e){}
            try { res = JSON.stringify(JSON.parse(res), null, 2); } catch(e){}
            $('#requestJson').text(req);
            $('#responseJson').text(res);
            $('#detailsModal').modal('show');
        });
    });
    </script>
</body>
</html>
