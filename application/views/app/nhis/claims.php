<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>NHIS Claims - Hebrew Medical Center</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">

    <link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />

    <style>
        .claim-status { font-size: 11px; padding: 4px 8px; }
        .table td { vertical-align: middle !important; }
        .filter-form .form-group { margin-bottom: 10px; }
    </style>
</head>
<body class="skin-blue">
    <?php require_once(APPPATH.'views/include/header.php');?>

    <div class="wrapper row-offcanvas row-offcanvas-left">
        <?php require_once(APPPATH.'views/include/sidebar.php');?>

        <aside class="right-side">
            <section class="content-header">
                <h1>NHIS Claims</h1>
                <ol class="breadcrumb">
                    <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                    <li><a href="<?php echo base_url()?>app/nhis">NHIS</a></li>
                    <li class="active">Claims</li>
                </ol>
            </section>

            <section class="content">
                <?php if ($this->session->flashdata('success')): ?>
                    <div class="alert alert-success alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                        <?php echo $this->session->flashdata('success'); ?>
                    </div>
                <?php endif; ?>
                <?php if ($this->session->flashdata('error')): ?>
                    <div class="alert alert-danger alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                        <?php echo $this->session->flashdata('error'); ?>
                    </div>
                <?php endif; ?>

                <!-- Filters -->
                <div class="box box-default collapsed-box">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-filter"></i> Filters</h3>
                        <div class="box-tools pull-right">
                            <button type="button" class="btn btn-box-tool" data-widget="collapse">
                                <i class="fa fa-plus"></i>
                            </button>
                        </div>
                    </div>
                    <div class="box-body">
                        <form method="get" class="filter-form">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Status</label>
                                        <select name="status" class="form-control">
                                            <option value="">All Statuses</option>
                                            <?php if (!empty($statuses)): ?>
                                                <?php foreach ($statuses as $key => $label): ?>
                                                    <option value="<?php echo $key; ?>" <?php echo (isset($filters['status']) && $filters['status'] == $key) ? 'selected' : ''; ?>>
                                                        <?php echo $label; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>From Date</label>
                                        <input type="date" name="from_date" class="form-control" 
                                               value="<?php echo isset($filters['from_date']) ? $filters['from_date'] : ''; ?>">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>To Date</label>
                                        <input type="date" name="to_date" class="form-control"
                                               value="<?php echo isset($filters['to_date']) ? $filters['to_date'] : ''; ?>">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Search</label>
                                        <input type="text" name="search" class="form-control" placeholder="Claim #, Patient..."
                                               value="<?php echo isset($filters['search']) ? $filters['search'] : ''; ?>">
                                    </div>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> Filter</button>
                            <a href="<?php echo base_url('app/nhis/claims'); ?>" class="btn btn-default">Reset</a>
                        </form>
                    </div>
                </div>

                <!-- Claims Table -->
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-list"></i> Claims List</h3>
                        <div class="box-tools pull-right">
                            <a href="<?php echo base_url('app/nhis/export_claims?' . http_build_query(isset($filters) ? $filters : array())); ?>" 
                               class="btn btn-sm btn-success">
                                <i class="fa fa-download"></i> Export CSV
                            </a>
                            &nbsp;
                            <a href="<?php echo base_url('app/nhis/export_claim_items?' . http_build_query(isset($filters) ? $filters : array())); ?>" 
                               class="btn btn-sm btn-info">
                                <i class="fa fa-table"></i> Export Line Items
                            </a>
                        </div>
                    </div>
                    <div class="box-body">
                        <form id="batchForm" method="post">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover table-striped">
                                    <thead>
                                        <tr>
                                            <th width="30"><input type="checkbox" id="selectAll"></th>
                                            <th>Claim #</th>
                                            <th>Patient</th>
                                            <th>NHIS ID</th>
                                            <th>Visit Date</th>
                                            <th>Type</th>
                                            <th>Amount</th>
                                            <th>Approved</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($claims)): ?>
                                            <?php foreach ($claims as $claim): ?>
                                                <tr>
                                                    <td>
                                                        <input type="checkbox" name="claim_ids[]" value="<?php echo $claim->id; ?>" class="claim-check">
                                                    </td>
                                                    <td>
                                                        <a href="<?php echo base_url('app/nhis/claim/' . $claim->id); ?>">
                                                            <strong><?php echo $claim->claim_number; ?></strong>
                                                        </a>
                                                    </td>
                                                    <td>
                                                        <?php echo $claim->firstname . ' ' . $claim->lastname; ?>
                                                        <br><small class="text-muted"><?php echo $claim->patient_no; ?></small>
                                                    </td>
                                                    <td><small><?php echo $claim->nhis_member_id; ?></small></td>
                                                    <td><?php echo date('d M Y', strtotime($claim->visit_date)); ?></td>
                                                    <td>
                                                        <span class="label label-<?php echo $claim->encounter_type == 'IPD' ? 'info' : 'default'; ?>">
                                                            <?php echo $claim->encounter_type; ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-right">GHS <?php echo number_format($claim->total_claim_amount, 2); ?></td>
                                                    <td class="text-right">
                                                        <?php if ($claim->approved_amount): ?>
                                                            GHS <?php echo number_format($claim->approved_amount, 2); ?>
                                                        <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                            $statusClass = 'default';
                                                            switch ($claim->claim_status) {
                                                                case 'approved': $statusClass = 'success'; break;
                                                                case 'rejected': $statusClass = 'danger'; break;
                                                                case 'submitted': $statusClass = 'info'; break;
                                                                case 'pending': case 'draft': $statusClass = 'warning'; break;
                                                                case 'paid': $statusClass = 'primary'; break;
                                                                case 'partial': $statusClass = 'warning'; break;
                                                            }
                                                        ?>
                                                        <span class="label label-<?php echo $statusClass; ?> claim-status">
                                                            <?php echo strtoupper($claim->claim_status); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group">
                                                            <a href="<?php echo base_url('app/nhis/claim/' . $claim->id); ?>" 
                                                               class="btn btn-xs btn-default" title="View">
                                                                <i class="fa fa-eye"></i>
                                                            </a>
                                                            <?php if (in_array($claim->claim_status, array('draft', 'pending'))): ?>
                                                                <a href="<?php echo base_url('app/nhis/submit_claim/' . $claim->id); ?>" 
                                                                   class="btn btn-xs btn-primary" title="Submit"
                                                                   onclick="return confirm('Submit this claim to NHIS?')">
                                                                    <i class="fa fa-send"></i>
                                                                </a>
                                                            <?php endif; ?>
                                                            <?php if ($claim->claim_status == 'submitted'): ?>
                                                                <a href="<?php echo base_url('app/nhis/check_claim_status/' . $claim->id); ?>" 
                                                                   class="btn btn-xs btn-info" title="Check Status">
                                                                    <i class="fa fa-refresh"></i>
                                                                </a>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="10" class="text-center text-muted">No claims found</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Batch Actions -->
                            <?php if (!empty($claims)): ?>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="btn-group">
                                        <button type="submit" formaction="<?php echo base_url('app/nhis/batch_submit'); ?>" 
                                                class="btn btn-primary" onclick="return confirm('Submit selected claims?')">
                                            <i class="fa fa-send"></i> Batch Submit
                                        </button>
                                        <button type="submit" formaction="<?php echo base_url('app/nhis/batch_check_status'); ?>" 
                                                class="btn btn-info">
                                            <i class="fa fa-refresh"></i> Check Status
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

            </section>
        </aside>
    </div>

    <script src="<?php echo base_url();?>public/js/jquery.min.js"></script>
    <script src="<?php echo base_url();?>public/js/bootstrap.min.js" type="text/javascript"></script>
    <script src="<?php echo base_url();?>public/js/AdminLTE/app.js" type="text/javascript"></script>
    <script>
        $(document).ready(function() {
            $('#selectAll').change(function() {
                $('.claim-check').prop('checked', $(this).is(':checked'));
            });
        });
    </script>
</body>
</html>
