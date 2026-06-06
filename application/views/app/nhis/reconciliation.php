<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>NHIS Reconciliation - Hebrew Medical Center</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">

    <link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />

    <style>
        .issue-type { font-size: 11px; }
        .table td { vertical-align: middle !important; }
    </style>
</head>
<body class="skin-blue">
    <?php require_once(APPPATH.'views/include/header.php');?>

    <div class="wrapper row-offcanvas row-offcanvas-left">
        <?php require_once(APPPATH.'views/include/sidebar.php');?>

        <aside class="right-side">
            <section class="content-header">
                <h1>NHIS Reconciliation</h1>
                <ol class="breadcrumb">
                    <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                    <li><a href="<?php echo base_url()?>app/nhis">NHIS</a></li>
                    <li class="active">Reconciliation</li>
                </ol>
            </section>

            <section class="content">
                <?php if ($this->session->flashdata('success')): ?>
                    <div class="alert alert-success alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                        <?php echo $this->session->flashdata('success'); ?>
                    </div>
                <?php endif; ?>

                <?php
                    $summary = isset($summary) ? $summary : array();
                    $openCount = isset($summary['open']) ? $summary['open'] : 0;
                    $resolvedCount = isset($summary['resolved']) ? $summary['resolved'] : 0;
                    $ignoredCount = isset($summary['ignored']) ? $summary['ignored'] : 0;
                ?>

                <!-- Summary Boxes -->
                <div class="row">
                    <div class="col-md-3">
                        <div class="small-box bg-red">
                            <div class="inner">
                                <h3><?php echo $openCount; ?></h3>
                                <p>Open Issues</p>
                            </div>
                            <div class="icon"><i class="fa fa-exclamation-triangle"></i></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="small-box bg-green">
                            <div class="inner">
                                <h3><?php echo $resolvedCount; ?></h3>
                                <p>Resolved</p>
                            </div>
                            <div class="icon"><i class="fa fa-check-circle"></i></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="small-box bg-gray">
                            <div class="inner">
                                <h3><?php echo $ignoredCount; ?></h3>
                                <p>Ignored</p>
                            </div>
                            <div class="icon"><i class="fa fa-ban"></i></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="small-box bg-blue">
                            <div class="inner">
                                <h3><?php echo $openCount + $resolvedCount + $ignoredCount; ?></h3>
                                <p>Total Issues</p>
                            </div>
                            <div class="icon"><i class="fa fa-list"></i></div>
                        </div>
                    </div>
                </div>

                <!-- Run Reconciliation -->
                <div class="box box-warning">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-play"></i> Run Reconciliation</h3>
                    </div>
                    <div class="box-body">
                        <form method="post" action="<?php echo base_url('app/nhis/run_reconciliation'); ?>" class="form-inline">
                            <div class="form-group">
                                <label>Date:</label>
                                <input type="date" name="date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <button type="submit" class="btn btn-warning" onclick="return confirm('Run reconciliation check?')">
                                <i class="fa fa-refresh"></i> Run Reconciliation
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Status Filter -->
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-list"></i> Reconciliation Issues</h3>
                        <div class="box-tools pull-right">
                            <div class="btn-group">
                                <a href="<?php echo base_url('app/nhis/reconciliation?status=open'); ?>" 
                                   class="btn btn-sm <?php echo (!isset($status) || $status == 'open') ? 'btn-danger' : 'btn-default'; ?>">
                                    Open
                                </a>
                                <a href="<?php echo base_url('app/nhis/reconciliation?status=resolved'); ?>" 
                                   class="btn btn-sm <?php echo (isset($status) && $status == 'resolved') ? 'btn-success' : 'btn-default'; ?>">
                                    Resolved
                                </a>
                                <a href="<?php echo base_url('app/nhis/reconciliation?status=ignored'); ?>" 
                                   class="btn btn-sm <?php echo (isset($status) && $status == 'ignored') ? 'btn-default active' : 'btn-default'; ?>">
                                    Ignored
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="box-body table-responsive no-padding">
                        <table class="table table-bordered table-hover table-striped">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Issue Type</th>
                                    <th>Reference</th>
                                    <th>Patient</th>
                                    <th class="text-right">Expected</th>
                                    <th class="text-right">Actual</th>
                                    <th class="text-right">Difference</th>
                                    <th>Description</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($issues)): ?>
                                    <?php foreach ($issues as $issue): ?>
                                        <tr>
                                            <td><?php echo date('d M Y', strtotime($issue->reconciliation_date)); ?></td>
                                            <td>
                                                <?php
                                                    $typeClass = 'default';
                                                    switch ($issue->issue_type) {
                                                        case 'covered_billed_patient': $typeClass = 'warning'; break;
                                                        case 'not_covered_billed_nhis': $typeClass = 'danger'; break;
                                                        case 'duplicate_claim': $typeClass = 'info'; break;
                                                        case 'missing_claim': $typeClass = 'primary'; break;
                                                        case 'amount_mismatch': $typeClass = 'danger'; break;
                                                    }
                                                ?>
                                                <span class="label label-<?php echo $typeClass; ?> issue-type">
                                                    <?php echo ucwords(str_replace('_', ' ', $issue->issue_type)); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small>
                                                    <?php echo ucfirst($issue->reference_type); ?>: 
                                                    <strong><?php echo $issue->reference_id; ?></strong>
                                                </small>
                                            </td>
                                            <td><?php echo $issue->patient_no ?: '-'; ?></td>
                                            <td class="text-right">
                                                <?php echo $issue->expected_amount ? 'GHS ' . number_format($issue->expected_amount, 2) : '-'; ?>
                                            </td>
                                            <td class="text-right">
                                                <?php echo $issue->actual_amount ? 'GHS ' . number_format($issue->actual_amount, 2) : '-'; ?>
                                            </td>
                                            <td class="text-right <?php echo $issue->difference_amount > 0 ? 'text-danger' : 'text-success'; ?>">
                                                <?php echo $issue->difference_amount ? 'GHS ' . number_format($issue->difference_amount, 2) : '-'; ?>
                                            </td>
                                            <td><small><?php echo $issue->description; ?></small></td>
                                            <td>
                                                <?php if ($issue->status == 'open'): ?>
                                                    <button type="button" class="btn btn-xs btn-success resolve-btn"
                                                            data-id="<?php echo $issue->id; ?>"
                                                            data-toggle="modal" data-target="#resolveModal">
                                                        <i class="fa fa-check"></i> Resolve
                                                    </button>
                                                <?php else: ?>
                                                    <span class="text-muted">
                                                        <small>
                                                            <?php echo $issue->resolved_by; ?><br>
                                                            <?php echo $issue->resolved_at ? date('d M', strtotime($issue->resolved_at)) : ''; ?>
                                                        </small>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="text-center text-muted">No issues found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </section>
        </aside>
    </div>

    <!-- Resolve Modal -->
    <div class="modal fade" id="resolveModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" action="" id="resolveForm">
                    <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                        <h4 class="modal-title"><i class="fa fa-check-circle"></i> Resolve Issue</h4>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Resolution Notes</label>
                            <textarea name="notes" class="form-control" rows="4" 
                                      placeholder="Describe how this issue was resolved..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fa fa-check"></i> Mark Resolved
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="<?php echo base_url();?>public/js/jquery.min.js"></script>
    <script src="<?php echo base_url();?>public/js/bootstrap.min.js" type="text/javascript"></script>
    <script src="<?php echo base_url();?>public/js/AdminLTE/app.js" type="text/javascript"></script>
    <script>
        $(document).ready(function() {
            $('.resolve-btn').click(function() {
                var id = $(this).data('id');
                $('#resolveForm').attr('action', '<?php echo base_url('app/nhis/resolve_issue/'); ?>' + id);
            });
        });
    </script>
</body>
</html>
